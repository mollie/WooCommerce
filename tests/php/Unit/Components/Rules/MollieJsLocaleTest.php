<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Components\Rules;

use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Components\Rules\MollieJsLocale;
use Mollie\WooCommerceTests\TestCase;

/**
 * The locale handed to Mollie.js: the WordPress locale mapped onto one Mollie accepts, checked
 * against the real AcceptedLocaleValuesDictionary.
 *
 * @covers \Mollie\WooCommerce\Components\Rules\MollieJsLocale
 */
class MollieJsLocaleTest extends TestCase
{
    /**
     * Scenario: the WordPress locale is mapped onto a locale Mollie accepts
     *   Given the shop's WordPress locale
     *   And the locales Mollie accepts and the default, from AcceptedLocaleValuesDictionary
     *   When the locale for Mollie.js is chosen
     *   Then a formal variant becomes its plain locale
     *   And an accepted locale is kept as it is
     *   And any other locale becomes the default en_US
     *
     * @dataProvider locales
     * @covers \Mollie\WooCommerce\Components\Rules\MollieJsLocale::from
     */
    public function testMapsTheWordPressLocaleOntoOneMollieAccepts(string $wordPressLocale, string $expected): void
    {
        self::assertSame(
            $expected,
            MollieJsLocale::from(
                $wordPressLocale,
                AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP,
                AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE
            )
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function locales(): array
    {
        return [
            'accepted, kept' => ['en_US', 'en_US'],
            'another accepted, kept' => ['nl_BE', 'nl_BE'],
            'German formal becomes German' => ['de_DE_formal', 'de_DE'],
            'Dutch formal becomes Dutch' => ['nl_NL_formal', 'nl_NL'],
            'not accepted, default' => ['xx_XX', 'en_US'],
            'language only, default' => ['de', 'en_US'],
            'accepted but other case, default' => ['EN_us', 'en_US'],
            'empty, default' => ['', 'en_US'],
        ];
    }

    /**
     * Scenario: the list and the default are arguments, not knowledge of the function
     *   Given a list that accepts only fr_FR and a default of fr_FR
     *   When an unknown locale is mapped
     *   Then the given default is returned, not en_US
     *
     * @covers \Mollie\WooCommerce\Components\Rules\MollieJsLocale::from
     */
    public function testFallsBackToTheDefaultItIsGiven(): void
    {
        self::assertSame('fr_FR', MollieJsLocale::from('de_DE', ['fr_FR'], 'fr_FR'));
        self::assertSame('de_DE', MollieJsLocale::from('de_DE_formal', ['de_DE'], 'fr_FR'));
    }
}
