<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\ShippingLineFromPayment;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
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
     *        payment's line, the VAT split from the gross at the order's shipping tax rate
     *   And an order that already holds a shipping line, ships nothing, or whose payment has no
     *        shipping_fee line gets none
     *
     * @dataProvider orders
     * @group REQ-NEW-EXPRESS-SHIPPING-LINE
     * @group REQ-C3
     * @param array<string, mixed> $order
     * @param array{rate: string, label: string, gross: string}|null $fee
     * @param array{rate: string, label: string, gross: string, net: string, tax: string}|null $expected
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
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>|null, 2: array<string, string>|null}>
     */
    public function orders(): array
    {
        $express = ['rate' => 'flat_rate:12', 'label' => 'Express', 'gross' => '12.10'];

        return [
            'ships, no line yet, 21% VAT' => [
                [],
                $express,
                ['rate' => 'flat_rate:12', 'label' => 'Express', 'gross' => '12.10', 'net' => '10.00', 'tax' => '2.10'],
            ],
            'ships, no line yet, 9% VAT' => [
                ['shippingTaxRate' => 9.0],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '10.90'],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '10.90', 'net' => '10.00', 'tax' => '0.90'],
            ],
            'ships, no line yet, no VAT on shipping' => [
                ['shippingTaxRate' => 0.0],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '5.00'],
                ['rate' => 'flat_rate:3', 'label' => 'Standard', 'gross' => '5.00', 'net' => '5.00', 'tax' => '0.00'],
            ],
            'free shipping option' => [
                [],
                ['rate' => 'free_shipping:4', 'label' => 'Free', 'gross' => '0.00'],
                ['rate' => 'free_shipping:4', 'label' => 'Free', 'gross' => '0.00', 'net' => '0.00', 'tax' => '0.00'],
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
            'shippingTaxRate' => 21.0,
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
            shippingTaxRate: $values['shippingTaxRate']
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
