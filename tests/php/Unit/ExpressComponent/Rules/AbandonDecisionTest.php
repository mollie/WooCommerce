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
 * no longer be paid, when Mollie does not know it, or when Mollie could not be asked for longer than
 * the give-up time. An answer that it may still be paid always keeps the order.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision
 */
class AbandonDecisionTest extends TestCase
{
    private const GIVE_UP_AFTER = 7 * 86400;

    /**
     * Scenario: every answer has a row
     *   Given whether the order is still pending, what Mollie reported for its payment or session, or
     *     that Mollie does not know it, or that Mollie could not be asked, and how long ago the session expired
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
        int $secondsSinceExpiry,
        bool $expectCancel,
        string $expectedReason
    ): void {

        $session = $sessionStatus === null ? null : new ExpressSession('sess_abc', $sessionStatus, '', '2026-09-21T10:15:00+00:00');
        $payment = $paymentStatus === null ? null : new PaymentSnapshot('tr_abc', $paymentStatus, 'paypal', Money::fromDecimal('26.05', 'EUR'));

        $verdict = AbandonDecision::decide($stillPending, $session, $payment, $unknownAtMollie, $secondsSinceExpiry, self::GIVE_UP_AFTER);

        self::assertSame([$expectCancel, $expectedReason], [$verdict->cancels(), $verdict->reason()]);
    }

    /**
     * @return array<string, array{0: bool, 1: ?string, 2: ?string, 3: bool, 4: int, 5: bool, 6: string}>
     */
    public function answers(): array
    {
        $hour = 3600;
        $longAgo = self::GIVE_UP_AFTER + 1;

        return [
            // The order knows no payment yet: the session answers.
            'session expired' => [true, 'expired', null, false, $hour, true, 'expired'],
            'session still open' => [true, 'open', null, false, $hour, false, 'open'],
            'session completed, payment not known to the order yet' => [true, 'completed', null, false, $hour, false, 'completed'],
            // The order knows its payment: the payment answers.
            'payment failed' => [true, null, 'failed', false, $hour, true, 'failed'],
            'payment canceled' => [true, null, 'canceled', false, $hour, true, 'canceled'],
            'payment expired' => [true, null, 'expired', false, $hour, true, 'expired'],
            'payment open' => [true, null, 'open', false, $hour, false, 'open'],
            'payment pending' => [true, null, 'pending', false, $hour, false, 'pending'],
            'payment authorized' => [true, null, 'authorized', false, $hour, false, 'authorized'],
            'payment paid' => [true, null, 'paid', false, $hour, false, 'paid'],
            // The payment wins over the session.
            'session expired, payment paid' => [true, 'expired', 'paid', false, $hour, false, 'paid'],
            'session completed, payment failed' => [true, 'completed', 'failed', false, $hour, true, 'failed'],
            'a status Mollie may add later' => [true, 'unknown_new_status', null, false, $hour, false, 'unknown_new_status'],
            // An answer that it may still be paid holds however old the order is.
            'payment pending for longer than the give-up time' => [true, null, 'pending', false, $longAgo, false, 'pending'],
            'session open for longer than the give-up time' => [true, 'open', null, false, $longAgo, false, 'open'],
            // Mollie does not know the session or the payment: nothing can pay it through this account.
            'Mollie does not know it' => [true, null, null, true, $hour, true, 'unknown_at_mollie'],
            // Mollie could not be asked: kept, until the give-up time has passed.
            'Mollie could not be asked' => [true, null, null, false, $hour, false, 'mollie_unreachable'],
            'Mollie could not be asked, exactly the give-up time after expiry' => [true, null, null, false, self::GIVE_UP_AFTER, false, 'mollie_unreachable'],
            'Mollie could not be asked for longer than the give-up time' => [true, null, null, false, $longAgo, true, 'unanswered'],
            // Another request changed the order while Mollie was asked: nothing is cancelled.
            'no longer pending, session expired' => [false, 'expired', null, false, $hour, false, 'no_longer_pending'],
            'no longer pending, Mollie does not know it' => [false, null, null, true, $hour, false, 'no_longer_pending'],
            'no longer pending, unanswered for longer than the give-up time' => [false, null, null, false, $longAgo, false, 'no_longer_pending'],
        ];
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

    /**
     * Scenario: cleanup gives up on Mollie only long after it started asking
     *   Given config/express.php
     *   When its give-up time is compared with the abandon grace
     *   Then an order is asked about for days before it is cancelled unanswered
     */
    public function testTheGiveUpTimeIsDaysLongerThanTheGrace(): void
    {
        $config = require PROJECT_DIR . '/config/express.php';

        self::assertSame(7 * 86400, $config['abandonGiveUpSeconds']);
        self::assertGreaterThan($config['abandonGraceSeconds'], $config['abandonGiveUpSeconds']);
    }
}
