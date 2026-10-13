<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\Api\Resources\Payment;

/**
 * Breaks one rule: a rule takes an SDK resource instead of a snapshot.
 */
final class TakesAnSdkResource
{
    public static function decide(Payment $payment): bool
    {
        return $payment->status === 'paid';
    }
}
