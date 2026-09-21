<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\Api\Resources\Payment;

/**
 * Breaks one rule: the core takes an SDK resource instead of a snapshot.
 */
final class TakesAnSdkResource
{
    public static function decide(Payment $payment): bool
    {
        return $payment->status === 'paid';
    }
}
