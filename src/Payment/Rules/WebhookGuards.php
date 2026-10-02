<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Rules;

final class WebhookGuards
{
    public const NOT_OURS = 'not_ours';
    public const SUPERSEDED = 'superseded';
    public const SETTLED = 'settled';
    public const PROCESS = 'process';

    // Not 'expired': onWebhookExpired handles an untracked payment itself.
    private const TERMINAL_STATUSES = ['failed', 'canceled', 'cancelled'];

    public static function needsPayment(
        bool $paidByOtherGateway,
        bool $paidAndProcessed,
        bool $authorized,
        bool $wcNeedsPayment,
        bool $onHoldAsInitialStatus
    ): bool {

        if ($paidByOtherGateway) {
            return false;
        }

        return !$paidAndProcessed || $authorized || $wcNeedsPayment || $onHoldAsInitialStatus;
    }

    public static function superseded(bool $tracksThisPayment, string $paymentStatus): bool
    {
        return !$tracksThisPayment && in_array($paymentStatus, self::TERMINAL_STATUSES, true);
    }

    public static function decide(
        bool $isMollieGateway,
        bool $tracksThisPayment,
        string $paymentStatus,
        bool $needsPayment
    ): string {

        if (!$isMollieGateway) {
            return self::NOT_OURS;
        }
        if (self::superseded($tracksThisPayment, $paymentStatus)) {
            return self::SUPERSEDED;
        }
        if (!$needsPayment) {
            return self::SETTLED;
        }

        return self::PROCESS;
    }
}
