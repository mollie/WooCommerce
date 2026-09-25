<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WordPress;

use RuntimeException;
/**
 * The per-order lock could not be taken in time, and nothing was written. Retryable: a webhook
 * caller answers non-2xx so Mollie comes back; any other caller shows a generic error.
 */
final class OrderLockTimeout extends RuntimeException
{
}
