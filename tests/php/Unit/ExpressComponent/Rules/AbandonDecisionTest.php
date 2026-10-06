<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether cleanup cancels a pending express order that outlived its session: when Mollie says it can
 * no longer be paid, or when Mollie, asked in the order's mode, does not know it. Any other answer,
 * and no answer, keeps the order.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision
 */
class AbandonDecisionTest extends TestCase
{
    /**
     * Scenario: every answer has a row
     *   Given whether the order is still pending, what Mollie reported for its payment or session, or
     *     that Mollie does not know it, or that Mollie could not be asked, the mode the order was
     *     created in and the mode of the key that asked
     *   When the decision is asked
     *   Then the order is cancelled or kept, with the reason
     *
     * @dataProvider answers
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision::decide
     */
    public function testDecidesWhetherAnAbandonedExpressOrderIsCancelled(
        bool $stillPending,
        ?string $sessionStatus,
        ?string $paymentStatus,
        bool $unknownAtMollie,
        string $askedInMode,
        bool $expectCancel,
        string $expectedReason
    ): void {

        $session = $sessionStatus === null ? null : new ExpressSession('sess_abc', $sessionStatus, '', '2026-09-21T10:15:00+00:00');
        $payment = $paymentStatus === null ? null : new PaymentSnapshot('tr_abc', $paymentStatus, 'paypal', Money::fromDecimal('26.05', 'EUR'));

        $verdict = AbandonDecision::decide($stillPending, $session, $payment, $unknownAtMollie, 'live', $askedInMode);

        self::assertSame([$expectCancel, $expectedReason], [$verdict->cancels(), $verdict->reason()]);
    }

    /**
     * The order is a live one in every row.
     *
     * @return array<string, array{0: bool, 1: ?string, 2: ?string, 3: bool, 4: string, 5: bool, 6: string}>
     */
    public function answers(): array
    {
        return [
            // The order knows no payment yet: the session answers.
            'session expired' => [true, 'expired', null, false, 'live', true, 'expired'],
            'session still open' => [true, 'open', null, false, 'live', false, 'open'],
            'session completed, payment not known to the order yet' => [true, 'completed', null, false, 'live', false, 'completed'],
            // The order knows its payment: the payment answers.
            'payment failed' => [true, null, 'failed', false, 'live', true, 'failed'],
            'payment canceled' => [true, null, 'canceled', false, 'live', true, 'canceled'],
            'payment expired' => [true, null, 'expired', false, 'live', true, 'expired'],
            'payment open' => [true, null, 'open', false, 'live', false, 'open'],
            'payment pending' => [true, null, 'pending', false, 'live', false, 'pending'],
            'payment authorized' => [true, null, 'authorized', false, 'live', false, 'authorized'],
            'payment paid' => [true, null, 'paid', false, 'live', false, 'paid'],
            // The payment wins over the session.
            'session expired, payment paid' => [true, 'expired', 'paid', false, 'live', false, 'paid'],
            'session completed, payment failed' => [true, 'completed', 'failed', false, 'live', true, 'failed'],
            'a status Mollie may add later' => [true, 'unknown_new_status', null, false, 'live', false, 'unknown_new_status'],
            // Mollie does not know the session or the payment: nothing can pay it through this account.
            'Mollie does not know it' => [true, null, null, true, 'live', true, 'unknown_at_mollie'],
            // A key of the other mode is told "not found" for every order: that says nothing about this one.
            'Mollie does not know it, asked in the other mode' => [true, null, null, true, 'test', false, 'asked_in_other_mode'],
            // Mollie could not be asked: kept.
            'Mollie could not be asked' => [true, null, null, false, 'live', false, 'mollie_unreachable'],
            'Mollie could not be asked, in the other mode' => [true, null, null, false, 'test', false, 'mollie_unreachable'],
            // Another request changed the order while Mollie was asked: nothing is cancelled.
            'no longer pending, session expired' => [false, 'expired', null, false, 'live', false, 'no_longer_pending'],
            'no longer pending, Mollie does not know it' => [false, null, null, true, 'live', false, 'no_longer_pending'],
        ];
    }

    /**
     * Scenario: an order whose mode was never recorded is not cancelled on "not found"
     *   Given a pending express order without a recorded mode
     *   When Mollie does not know its session
     *   Then the order is kept: the key that asked cannot be told to be the right one
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision::decide
     */
    public function testKeepsAnOrderWithoutARecordedModeThatMollieDoesNotKnow(): void
    {
        $verdict = AbandonDecision::decide(true, null, null, true, '', 'live');

        self::assertSame([false, 'asked_in_other_mode'], [$verdict->cancels(), $verdict->reason()]);
    }

    /**
     * Scenario: cleanup waits longer than a checkout session can live
     *   Given config/express.php
     *   When its abandon grace is compared with the session lifetime
     *   Then cleanup looks at an order only a full session lifetime or more after its session expired,
     *        so a payment started at the last moment has had as long again to arrive
     */
    public function testTheCleanupGraceIsLongerThanASessionLives(): void
    {
        $config = require PROJECT_DIR . '/config/express.php';

        self::assertGreaterThan($config['sessionLifetimeSeconds'], $config['abandonGraceSeconds']);
    }
}
