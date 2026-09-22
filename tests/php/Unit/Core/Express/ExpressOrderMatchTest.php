<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\ExpressOrderMatch;
use Mollie\WooCommerce\Core\Types\Admit;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Mollie\WooCommerce\Core\Types\Refuse;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether a payment the plugin never created may be tied to the order that carries its express_ref
 * (REQ-D1, REQ-G5; AC-20, AC-39).
 *
 * The payment was fetched from Mollie, never taken from the request. The candidate order is the one
 * whose _mollie_express_ref equals the ref in the payment's metadata, or none. A match needs all of:
 * the ref is present and the order carries that same ref, the order was created by the express flow,
 * the payment's amount and currency equal the order's total, and the order still needs payment or
 * already tracks this very payment. Anything else is refused with its own reason, answered 200 so
 * Mollie stops retrying, and nothing is written.
 *
 * @covers \Mollie\WooCommerce\Core\Express\ExpressOrderMatch
 */
class ExpressOrderMatchTest extends TestCase
{
    private const REF = 'exr_0123456789abcdef0123456789abcdef';

    /**
     * Scenario: a pending express order carrying the payment's ref and total is matched
     *   Given a payment whose metadata carries the express_ref of a pending express order
     *   And the order knows no payment id yet
     *   And the payment's amount and currency equal the order's total
     *   When the match is decided
     *   Then it is admitted
     *
     * @covers \Mollie\WooCommerce\Core\Express\ExpressOrderMatch::decide
     */
    public function testMatchesAPendingExpressOrderCarryingTheSameRef(): void
    {
        $decision = ExpressOrderMatch::decide($this->payment(), $this->order());

        self::assertInstanceOf(Admit::class, $decision);
    }

    /**
     * Scenario: an order that already tracks this payment is matched again, so a retry stays harmless
     *   Given an express order that is paid and whose _mollie_payment_id is this payment
     *   When the match is decided for the same payment
     *   Then it is admitted, and the rails downstream find the order already settled
     *
     * @covers \Mollie\WooCommerce\Core\Express\ExpressOrderMatch::decide
     */
    public function testMatchesAnOrderThatAlreadyTracksThisPayment(): void
    {
        $decision = ExpressOrderMatch::decide(
            $this->payment(),
            $this->order(['trackedPaymentId' => 'tr_express1', 'needsPayment' => false])
        );

        self::assertInstanceOf(Admit::class, $decision);
    }

    /**
     * Scenario: every mismatch is refused with a reason of its own
     *   Given a payment and a candidate order that differ in exactly one way
     *   When the match is decided
     *   Then it is refused with that way's reason code
     *   And it is answered 200, so Mollie does not retry a notification that will never match
     *
     * @dataProvider mismatches
     * @covers \Mollie\WooCommerce\Core\Express\ExpressOrderMatch::decide
     * @param array<string, mixed> $paymentOverrides
     * @param array<string, mixed>|null $orderOverrides Null when no order carries the ref.
     */
    public function testRefusesEachMismatchWithItsOwnReason(
        array $paymentOverrides,
        ?array $orderOverrides,
        string $expectedReason
    ): void {

        $order = $orderOverrides === null ? null : $this->order($orderOverrides);

        $decision = ExpressOrderMatch::decide($this->payment($paymentOverrides), $order);

        self::assertInstanceOf(Refuse::class, $decision);
        self::assertSame($expectedReason, $decision->code());
        self::assertSame(200, $decision->httpStatus());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>|null, 2: string}>
     */
    public function mismatches(): array
    {
        return [
            'metadata carries no ref' => [['expressRef' => null], [], 'missing_ref'],
            'metadata carries an empty ref' => [['expressRef' => ''], [], 'missing_ref'],
            'no order carries the ref' => [[], null, 'unknown_ref'],
            'the candidate carries a different ref' => [[], ['expressRef' => 'exr_ffffffffffffffffffffffffffffffff'], 'unknown_ref'],
            'the order was created by the ordinary checkout' => [[], ['createdVia' => 'checkout'], 'not_express'],
            'the order has no created_via' => [[], ['createdVia' => null], 'not_express'],
            'the amount differs by a cent' => [['amount' => Money::fromDecimal('26.04', 'EUR')], [], 'amount_mismatch'],
            'the currency differs' => [['amount' => Money::fromDecimal('26.05', 'USD')], [], 'amount_mismatch'],
            'the order tracks a different payment' => [[], ['trackedPaymentId' => 'tr_other'], 'other_payment'],
            'a paid order tracks a different payment' => [[], ['trackedPaymentId' => 'tr_other', 'needsPayment' => false], 'other_payment'],
            'the order no longer needs payment and tracks none' => [[], ['needsPayment' => false], 'not_payable'],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function payment(array $overrides = []): PaymentSnapshot
    {
        $values = array_merge([
            'id' => 'tr_express1',
            'status' => 'paid',
            'method' => 'paypal',
            'amount' => Money::fromDecimal('26.05', 'EUR'),
            'expressRef' => self::REF,
        ], $overrides);

        return new PaymentSnapshot(
            $values['id'],
            $values['status'],
            $values['method'],
            $values['amount'],
            null,
            mode: 'live',
            expressRef: $values['expressRef']
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): ExpressOrderFacts
    {
        $values = array_merge([
            'expressRef' => self::REF,
            'createdVia' => 'mollie_express',
            'total' => Money::fromDecimal('26.05', 'EUR'),
            'trackedPaymentId' => null,
            'needsPayment' => true,
        ], $overrides);

        return new ExpressOrderFacts(
            expressRef: $values['expressRef'],
            existingOrderId: 42,
            createdVia: $values['createdVia'],
            total: $values['total'],
            trackedPaymentId: $values['trackedPaymentId'],
            needsPayment: $values['needsPayment']
        );
    }
}
