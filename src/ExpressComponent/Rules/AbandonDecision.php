<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\AbandonVerdict;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
/**
 * A known payment outranks the session; without an answer about the order, it is kept.
 */
final class AbandonDecision
{
    public const NO_LONGER_PENDING = 'no_longer_pending';
    public const UNKNOWN_AT_MOLLIE = 'unknown_at_mollie';
    public const UNREACHABLE = 'mollie_unreachable';
    public const ASKED_IN_OTHER_MODE = 'asked_in_other_mode';
    private const FINAL_PAYMENT = ['failed', 'canceled', 'expired'];
    private const FINAL_SESSION = ['expired'];
    /**
     * @param bool $unknownAtMollie Mollie answered that it holds no such session or payment.
     * @param string $orderMode 'live' or 'test', as the order was created.
     * @param string $askedInMode The mode of the key that asked.
     */
    public static function decide(bool $stillPending, ?ExpressSession $session, ?PaymentSnapshot $payment, bool $unknownAtMollie, string $orderMode, string $askedInMode): AbandonVerdict
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
        if (!$unknownAtMollie) {
            return AbandonVerdict::keep(self::UNREACHABLE);
        }
        // A key of the other mode gets "not found" for every order.
        return $orderMode === $askedInMode ? AbandonVerdict::cancel(self::UNKNOWN_AT_MOLLIE) : AbandonVerdict::keep(self::ASKED_IN_OTHER_MODE);
    }
    /**
     * @param list<string> $final
     */
    private static function answered(string $status, array $final): AbandonVerdict
    {
        return in_array($status, $final, \true) ? AbandonVerdict::cancel($status) : AbandonVerdict::keep($status);
    }
}
