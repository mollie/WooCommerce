<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerce\Core\Types\ExpressSession;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;

/**
 * Whether cleanup may cancel a pending express order past its expiry and grace.
 *
 * Only a positive "can no longer be paid" from Mollie cancels: the payment failed, was canceled or
 * expired, or, when the order knows no payment, the session expired. When the order knows its
 * payment, the payment answers. No answer at all keeps the order.
 */
final class AbandonDecision
{
    public const NOTE = 'express.order.abandoned';

    private const FINAL_PAYMENT = ['failed', 'canceled', 'expired'];

    private const FINAL_SESSION = ['expired'];

    /**
     * @return list<Effect> Empty to keep the order.
     */
    public static function decide(?ExpressSession $session, ?PaymentSnapshot $payment): array
    {
        $cannotBePaid = match (true) {
            $payment !== null => in_array($payment->status(), self::FINAL_PAYMENT, true),
            $session !== null => in_array($session->status(), self::FINAL_SESSION, true),
            default => false,
        };

        return $cannotBePaid
            ? [Effect::setStatus('cancelled'), Effect::addNote(self::NOTE)]
            : [];
    }
}
