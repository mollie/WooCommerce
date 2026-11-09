<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * A wallet's express switch for one page: what the merchant saved, else the default.
 */
final class ExpressSwitch
{
    /**
     * @param string|null $stored The saved value, null when the switch was never saved.
     */
    public static function effective(?string $stored, bool $onByDefault): bool
    {
        if ($stored === null) {
            return $onByDefault;
        }

        return in_array(strtolower($stored), ['yes', 'true', '1'], true);
    }
}
