<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\AbandonVerdict;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
/**
 * A known payment outranks the session; "may still be paid" keeps the order however old it is.
 */
final class AbandonDecision
{
    public const NO_LONGER_PENDING = 'no_longer_pending';
    public const UNKNOWN_AT_MOLLIE = 'unknown_at_mollie';
    public const UNREACHABLE = 'mollie_unreachable';
    public const UNANSWERED = 'unanswered';
    private const FINAL_PAYMENT = ['failed', 'canceled', 'expired'];
    private const FINAL_SESSION = ['expired'];
    /**
     * @param bool $unknownAtMollie Mollie answered that it holds no such session or payment.
     * @param int $secondsSinceExpiry Since the order's session expired.
     */
    public static function decide(bool $stillPending, ?ExpressSession $session, ?PaymentSnapshot $payment, bool $unknownAtMollie, int $secondsSinceExpiry, int $giveUpAfterSeconds): AbandonVerdict
    {
        if (!$stillPending) {
            return AbandonVerdict::keep(self::NO_LONGER_PENDING);
        }
        if ($payment !== null) {
            return self::answered($payment->status(), self::FINAL_PAYMENT);
        }
        if ($session !== null) {
            return self::answered($session->status(), self::FINAL_SESSION);
        }
        if ($unknownAtMollie) {
            return AbandonVerdict::cancel(self::UNKNOWN_AT_MOLLIE);
        }
        return $secondsSinceExpiry > $giveUpAfterSeconds ? AbandonVerdict::cancel(self::UNANSWERED) : AbandonVerdict::keep(self::UNREACHABLE);
    }
    /**
     * @param list<string> $final
     */
    private static function answered(string $status, array $final): AbandonVerdict
    {
        return in_array($status, $final, \true) ? AbandonVerdict::cancel($status) : AbandonVerdict::keep($status);
    }
}
