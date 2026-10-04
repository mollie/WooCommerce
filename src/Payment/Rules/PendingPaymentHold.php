<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Rules;

final class PendingPaymentHold
{
    private const PENDING = 'pending';
    private const ON_HOLD = 'on-hold';
    // Methods Mollie documents as staying 'pending' after the shopper has done their part.
    private const HELD_METHODS = ['paybybank'];
    public static function holds(string $mollieStatus, string $mollieMethod, string $orderStatus, string $initialOrderStatus): bool
    {
        return $mollieStatus === self::PENDING && in_array($mollieMethod, self::HELD_METHODS, \true) && $orderStatus === self::PENDING && $initialOrderStatus === self::ON_HOLD;
    }
}
