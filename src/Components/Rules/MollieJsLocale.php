<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Components\Rules;

final class MollieJsLocale
{
    /**
     * @param array<int, string> $allowed
     */
    public static function from(string $locale, array $allowed, string $default): string
    {
        $locale = str_replace('_formal', '', $locale);

        return in_array($locale, $allowed, true) ? $locale : $default;
    }
}
