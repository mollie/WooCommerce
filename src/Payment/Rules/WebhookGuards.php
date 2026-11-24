<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Rules;

final class WebhookGuards
{
    public const NOT_OURS = 'not_ours';
    public const SUPERSEDED = 'superseded';
    public const SETTLED = 'settled';
    public const PROCESS = 'process';
    public const PAID_ELSEWHERE = 'paid_elsewhere';
    // Not 'expired': onWebhookExpired handles an untracked payment itself.
    private const TERMINAL_STATUSES = ['failed', 'canceled', 'cancelled'];
    private const MONEY_TAKEN_STATUSES = ['paid', 'authorized'];
    public static function needsPayment(bool $paidByOtherGateway, bool $paidAndProcessed, bool $authorized, bool $wcNeedsPayment, bool $onHoldAsInitialStatus): bool
    {
        if ($paidByOtherGateway) {
            return \false;
        }
        return !$paidAndProcessed || $authorized || $wcNeedsPayment || $onHoldAsInitialStatus;
    }
    /**
     * An untracked payment acts on the order only when Mollie's record of it names this order,
     * and even then never with a terminal status.
     */
    public static function superseded(bool $tracksThisPayment, bool $createdForOrder, string $paymentStatus): bool
    {
        if ($tracksThisPayment) {
            return \false;
        }
        return !$createdForOrder || in_array($paymentStatus, self::TERMINAL_STATUSES, \true);
    }
    public static function decide(bool $isMollieGateway, bool $tracksThisPayment, bool $createdForOrder, string $paymentStatus, bool $needsPayment, bool $inLinkedMollieOrder = \false): string
    {
        if (!$isMollieGateway) {
            return self::NOT_OURS;
        }
        if (self::superseded($tracksThisPayment, $createdForOrder, $paymentStatus)) {
            return self::SUPERSEDED;
        }
        if (!$needsPayment) {
            if (!$tracksThisPayment && !$inLinkedMollieOrder && in_array($paymentStatus, self::MONEY_TAKEN_STATUSES, \true)) {
                return self::PAID_ELSEWHERE;
            }
            return self::SETTLED;
        }
        return self::PROCESS;
    }
}
