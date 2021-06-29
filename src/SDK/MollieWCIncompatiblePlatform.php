<?php

namespace Mollie\WooCommerce\SDK;

use Mollie\WooCommerce\Utils\MollieException;

class MollieWCIncompatiblePlatform extends MollieException
{
    const API_CLIENT_NOT_INSTALLED    = 1000;
    const API_CLIENT_NOT_COMPATIBLE   = 2000;
}
