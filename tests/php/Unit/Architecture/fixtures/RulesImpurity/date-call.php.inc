<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * Breaks one rule: formatting "now" is not deterministic, and depends on the site timezone.
 */
final class FormatsNow
{
    public static function decide(): string
    {
        return date('c');
    }
}
