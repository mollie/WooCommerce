<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\ShippingLineFromPayment;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingTaxRate;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Mollie\WooCommerce\Shared\Values\ShippingFee;
use Mollie\WooCommerceTests\TestCase;

/**
 * The shipping line the first webhook puts on an express order: what the shopper chose in the wallet,
 * read from the payment's shipping_fee line, only for an order that ships and holds no shipping line yet.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\ShippingLineFromPayment
 */
class ShippingLineFromPaymentTest extends TestCase
{
    /**
     * Scenario: the shipping line follows the payment, once, and only for a cart that ships
     *   Given an express order and a paid payment, with or without a shipping_fee line
     *   When the shipping line is decided
     *   Then an order that ships and holds no line gets the rate id, label and gross amount of the
     *        payment's line, the tax split from the gross per shipping tax rate of the order, as WooCommerce
     *        splits a price that includes tax: compound rates first, regular rates in proportion
     *   And an order that already holds a shipping line, ships nothing, or whose payment has no
     *        shipping_fee line gets none
     *
     * @dataProvider orders
     * @group REQ-NEW-EXPRESS-SHIPPING-LINE
     * @group REQ-C3
     * @param array<string, mixed> $order
     * @param array{rate: string, label: string, gross: string}|null $fee
     * @param array{rate: string, label: string, gross: string, net: string, tax: string, taxes: array<int, string>}|null $expected
     */
    public function testDecidesTheShippingLineFromTheShippingFeeOfThePayment(array $order, ?array $fee, ?array $expected): void
    {
        $line = ShippingLineFromPayment::decide($this->order($order), $this->payment($fee));

        if ($expected === null) {
            self::assertNull($line);

            return;
        }
        self::assertNotNull($line);
        self::assertSame($expected['rate'], $line->rateId());
        self::assertSame($expected['label'], $line->label());
        self::assertSame($expected['gross'], $line->gross()->toDecimal());
        self::assertSame($expected['net'], $line->net()->toDecimal());
        self::assertSame($expected['tax'], $line->tax()->toDecimal());
        self::assertSame(
            $expected['taxes'],
            array_map(static fn (Money $tax): string => $tax->toDecimal(), $line->taxes())
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>|null, 2: array<string, mixed>|null}>
     */
    public function orders(): array
    {
        $express = ['rate' => 'flat_rate:12', 'label' => 'Express', 'gross' => '12.10'];

        return [
            'ships, no line yet, 21% VAT' => [
                [],
                $express,
                ['rate' => 'flat_rate:12', 'label' => 'Express', 'gross' => '12.10', 'net' => '10.00', 'tax' => '2.10', 'taxes' => [1 => '2.10']],
            ],
            'ships, no line yet, 9% VAT' => [
                ['shippingTaxRates' => [new ShippingTaxRate(3, 9.0, false)]],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '10.90'],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '10.90', 'net' => '10.00', 'tax' => '0.90', 'taxes' => [3 => '0.90']],
            ],
            'ships, no line yet, no tax on shipping' => [
                ['shippingTaxRates' => []],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '5.00'],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '5.00', 'net' => '5.00', 'tax' => '0.00', 'taxes' => []],
            ],
            '21% VAT, the split rounds to the cent' => [
                [],
                ['rate' => 'flat_rate:12', 'label' => 'Express', 'gross' => '9.99'],
                ['rate' => 'flat_rate:12', 'label' => 'Express', 'gross' => '9.99', 'net' => '8.26', 'tax' => '1.73', 'taxes' => [1 => '1.73']],
            ],
            'two regular rates (5% and 7%) each get their share' => [
                ['shippingTaxRates' => [new ShippingTaxRate(5, 5.0, false), new ShippingTaxRate(7, 7.0, false)]],
                ['rate' => 'flat_rate:2', 'label' => 'Ground', 'gross' => '11.20'],
                ['rate' => 'flat_rate:2', 'label' => 'Ground', 'gross' => '11.20', 'net' => '10.00', 'tax' => '1.20', 'taxes' => [5 => '0.50', 7 => '0.70']],
            ],
            'a compound rate (5%) on a regular rate (10%)' => [
                ['shippingTaxRates' => [new ShippingTaxRate(10, 10.0, false), new ShippingTaxRate(11, 5.0, true)]],
                ['rate' => 'flat_rate:2', 'label' => 'Ground', 'gross' => '11.55'],
                ['rate' => 'flat_rate:2', 'label' => 'Ground', 'gross' => '11.55', 'net' => '10.00', 'tax' => '1.55', 'taxes' => [10 => '1.00', 11 => '0.55']],
            ],
            'free shipping option' => [
                [],
                ['rate' => 'free_shipping:4', 'label' => 'Free', 'gross' => '0.00'],
                ['rate' => 'free_shipping:4', 'label' => 'Free', 'gross' => '0.00', 'net' => '0.00', 'tax' => '0.00', 'taxes' => [1 => '0.00']],
            ],
            'a second webhook: the order holds its line' => [['holdsShippingLine' => true], $express, null],
            'ships nothing' => [['needsShipping' => false], $express, null],
            'ships, the payment has no shipping_fee line' => [[], null, null],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides): ExpressOrderFacts
    {
        $values = array_merge([
            'needsShipping' => true,
            'holdsShippingLine' => false,
            'shippingTaxRates' => [new ShippingTaxRate(1, 21.0, false)],
        ], $overrides);

        return new ExpressOrderFacts(
            orderId: 42,
            expressRef: 'exr_0123456789abcdef0123456789abcdef',
            createdVia: 'mollie_express',
            total: Money::fromDecimal('26.05', 'EUR'),
            trackedPaymentId: null,
            needsPayment: true,
            holdsShipping: false,
            needsShipping: $values['needsShipping'],
            holdsShippingLine: $values['holdsShippingLine'],
            shippingTaxRates: $values['shippingTaxRates']
        );
    }

    /**
     * @param array{rate: string, label: string, gross: string}|null $fee
     */
    private function payment(?array $fee): PaymentSnapshot
    {
        return new PaymentSnapshot(
            'tr_express1',
            'paid',
            'paypal',
            Money::fromDecimal('38.15', 'EUR'),
            expressRef: 'exr_0123456789abcdef0123456789abcdef',
            shippingFee: $fee === null ? null : new ShippingFee($fee['rate'], $fee['label'], Money::fromDecimal($fee['gross'], 'EUR'))
        );
    }
}
