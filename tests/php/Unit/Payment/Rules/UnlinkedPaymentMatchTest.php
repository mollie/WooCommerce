<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Rules;

use Mollie\WooCommerce\Payment\Rules\UnlinkedPaymentMatch;
use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\Refuse;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\Rules\UnlinkedPaymentMatch
 */
class UnlinkedPaymentMatchTest extends TestCase
{
    /**
     * Scenario: a Mollie order no order is linked to yet is handed to the order its metadata names only when that order can take it
     *   Given whether the resource is a Mollie order, the order id in its metadata, what the shop knows of that order, and both amounts
     *   When admit() is asked
     *   Then it admits only a Mollie order naming an existing order with the same number, of a Mollie gateway, still needing payment, for the same amount and currency
     *
     * @dataProvider cases
     * @group REQ-337
     */
    public function testAdmitsOnlyAPayableMollieOrderOfTheSameAmount(
        bool $isMollieOrder,
        int $metadataOrderId,
        bool $orderExists,
        bool $orderNumberMatches,
        bool $isMollieGateway,
        bool $orderNeedsPayment,
        ?Money $orderTotal,
        ?string $refusal,
        bool $paymentAmountReadable = true
    ): void {

        $verdict = UnlinkedPaymentMatch::admit(
            $isMollieOrder,
            $metadataOrderId,
            $orderExists,
            $orderNumberMatches,
            $isMollieGateway,
            $orderNeedsPayment,
            $paymentAmountReadable ? Money::fromDecimal('11.01', 'EUR') : null,
            $orderTotal
        );

        if ($refusal === null) {
            self::assertInstanceOf(Admit::class, $verdict);

            return;
        }
        self::assertInstanceOf(Refuse::class, $verdict);
        self::assertSame($refusal, $verdict->code());
        self::assertSame(200, $verdict->httpStatus());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function cases(): array
    {
        $same = Money::fromDecimal('11.01', 'EUR');

        return [
            'a payable Mollie order of the same amount' => [true, 66, true, true, true, true, $same, null],
            'a payment, not a Mollie order (express, Payments API)' => [false, 66, true, true, true, true, $same, UnlinkedPaymentMatch::NOT_A_MOLLIE_ORDER],
            'no order id in the metadata' => [true, 0, false, false, false, false, null, UnlinkedPaymentMatch::NO_ORDER_ID],
            'the named order does not exist' => [true, 66, false, false, false, false, null, UnlinkedPaymentMatch::ORDER_UNKNOWN],
            'the Mollie order carries another order number' => [true, 66, true, false, true, true, $same, UnlinkedPaymentMatch::ORDER_NUMBER_MISMATCH],
            'the order is not paid through Mollie' => [true, 66, true, true, false, true, $same, UnlinkedPaymentMatch::NOT_MOLLIE],
            'the order needs no payment' => [true, 66, true, true, true, false, $same, UnlinkedPaymentMatch::NOT_PAYABLE],
            'another amount' => [true, 66, true, true, true, true, Money::fromDecimal('25.30', 'EUR'), UnlinkedPaymentMatch::AMOUNT_MISMATCH],
            'another currency' => [true, 66, true, true, true, true, Money::fromDecimal('11.01', 'USD'), UnlinkedPaymentMatch::AMOUNT_MISMATCH],
            'no readable order total' => [true, 66, true, true, true, true, null, UnlinkedPaymentMatch::AMOUNT_MISMATCH],
            'no readable payment amount' => [true, 66, true, true, true, true, $same, UnlinkedPaymentMatch::AMOUNT_MISMATCH, false],
        ];
    }
}
