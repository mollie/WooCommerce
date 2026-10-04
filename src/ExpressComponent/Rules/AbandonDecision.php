<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;

/**
 * True only on a definite "can no longer be paid"; a known payment outranks the session.
 */
final class AbandonDecision
{
    private const FINAL_PAYMENT = ['failed', 'canceled', 'expired'];

    private const FINAL_SESSION = ['expired'];

    public static function decide(?ExpressSession $session, ?PaymentSnapshot $payment): bool
    {
        return match (true) {
            $payment !== null => in_array($payment->status(), self::FINAL_PAYMENT, true),
            $session !== null => in_array($session->status(), self::FINAL_SESSION, true),
            default => false,
        };
    }
}
