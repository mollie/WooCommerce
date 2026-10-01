<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\AbandonDecision;
use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerce\Core\Types\ExpressSession;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether cleanup may cancel a pending express order that outlived its session (REQ-E1 to E4;
 * AC-27 to AC-30).
 *
 * The order is already past its expiry and grace; this is only what Mollie says about it. An order
 * is cancelled only when Mollie positively says it can no longer be paid: the payment failed, was
 * canceled or expired, or — when the order knows no payment yet — the session expired. Anything
 * that could still succeed keeps it, and so does not knowing: when Mollie could not be reached the
 * workflow passes neither a session nor a payment. When the order knows its payment, the payment
 * is what counts, whatever the session says.
 *
 * @covers \Mollie\WooCommerce\Core\Express\AbandonDecision
 */
class AbandonDecisionTest extends TestCase
{
    /**
     * Scenario: every Mollie status has a row, and only a final "cannot be paid" cancels
     *   Given what Mollie reported for the order's session or payment, or that it could not be reached
     *   When the decision is asked
     *   Then a status that can no longer be paid gives the cancelled status and the abandon note
     *   And every other status, and no answer at all, gives no effect
     *
     * @dataProvider mollieAnswers
     * @covers \Mollie\WooCommerce\Core\Express\AbandonDecision::decide
     */
    public function testDecidesWhetherAnAbandonedExpressOrderIsCancelled(
        ?string $sessionStatus,
        ?string $paymentStatus,
        bool $expectCancel
    ): void {

        $session = $sessionStatus === null ? null : new ExpressSession('sess_abc', $sessionStatus, '', '2026-09-21T10:15:00+00:00');
        $payment = $paymentStatus === null ? null : new PaymentSnapshot('tr_abc', $paymentStatus, 'paypal', Money::fromDecimal('26.05', 'EUR'));

        $effects = AbandonDecision::decide($session, $payment);

        if (!$expectCancel) {
            self::assertSame([], $effects);

            return;
        }
        self::assertSame([
            [Effect::SET_STATUS, ['status' => 'cancelled']],
            [Effect::ADD_NOTE, ['messageKey' => AbandonDecision::NOTE, 'params' => []]],
        ], array_map(static function (Effect $effect): array {
            return [$effect->type(), $effect->data()];
        }, $effects));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool}>
     */
    public function mollieAnswers(): array
    {
        return [
            // The order knows no payment yet: the session answers.
            'session expired' => ['expired', null, true],
            'session still open' => ['open', null, false],
            'session completed, payment not known to the order yet' => ['completed', null, false],
            // The order knows its payment: the payment answers.
            'payment failed' => [null, 'failed', true],
            'payment canceled' => [null, 'canceled', true],
            'payment expired' => [null, 'expired', true],
            'payment open' => [null, 'open', false],
            'payment pending' => [null, 'pending', false],
            'payment authorized' => [null, 'authorized', false],
            'payment paid' => [null, 'paid', false],
            // The payment wins over the session.
            'session expired, payment paid' => ['expired', 'paid', false],
            'session completed, payment failed' => ['completed', 'failed', true],
            // Not knowing is never a reason to cancel.
            'Mollie could not be reached' => [null, null, false],
            'a status Mollie may add later' => ['unknown_new_status', null, false],
        ];
    }
}
