<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Mollie\WooCommerce\Shared\MollieException;

class MollieWCIncompatiblePlatform extends MollieException
{
    /**
     * @var int
     */
    public const API_CLIENT_NOT_INSTALLED = 1000;
    /**
     * @var int
     */
    public const API_CLIENT_NOT_COMPATIBLE = 2000;
}
