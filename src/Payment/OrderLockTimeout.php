<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use RuntimeException;
/**
 * Retryable: nothing was written; a webhook answers non-2xx so Mollie retries.
 */
final class OrderLockTimeout extends RuntimeException
{
}
