<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Payment;

/**
 * The locale handed to Mollie.js: the shop's WordPress locale mapped onto one Mollie accepts.
 *
 * A formal variant ('de_DE_formal') counts as its plain locale; anything not in the accepted list,
 * compared exactly, becomes the default. The list and the default are arguments, so the rule knows
 * nothing about where they are kept (REQ-513).
 */
final class MollieJsLocale
{
    /**
     * @param array<int, string> $allowed Locales Mollie accepts.
     */
    public static function from(string $locale, array $allowed, string $default): string
    {
        $locale = str_replace('_formal', '', $locale);
        return in_array($locale, $allowed, \true) ? $locale : $default;
    }
}
