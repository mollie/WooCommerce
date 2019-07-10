<?php # -*- coding: utf-8 -*-

use function Brain\Monkey\Filters\expectApplied as expectFilterApplied;
use function Brain\Monkey\Functions\expect;
use Mollie\WooCommerce\Tests\TestCase;

use Mollie_WC_Helper_Settings as Testee;

/**
 * Class MollieWCHelperSettingsTest
 */
class MollieWCHelperSettingsTest extends TestCase
{
    /* -----------------------------------------------------------------
       getPaymentLocale Tests
       -------------------------------------------------------------- */

    /**
     * Test the default payment locale is returned if no options has been specified.
     */
    public function testGetPaymentLocale()
    {
        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'getPaymentLocale',
            ['getPaymentLocaleSetting']
        );

        /*
         * Expect to call getPaymentLocaleSettings and return a value that is not
         * a valid option for locale.
         *
         * This way the language returned will be the default language.
         */
        $testee
            ->expects($this->once())
            ->method('getPaymentLocaleSetting')
            ->willReturn('');

        /*
         * Execute test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals(Testee::SETTING_LOCALE_DEFAULT_LANGUAGE, $result);
    }

    /**
     * Test WP Locale value is returned
     */
    public function testGetPaymentLocaleReturnsWpLocale()
    {
        /*
         * Stubs
         */
        $validLanguageCode = 'en_US';

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'getPaymentLocale',
            [
                'getPaymentLocaleSetting',
                'getCurrentLocale',
            ]
        );

        /*
         * Expect getPaymentLocaleSettings returns Testee::SETTING_LOCALE_WP_LANGUAGE_CODE
         */
        $testee
            ->expects($this->once())
            ->method('getPaymentLocaleSetting')
            ->willReturn(Testee::SETTING_LOCALE_WP_LANGUAGE);

        /*
         * Then expect getCurrentLocale is called and return a valid language code value.
         */
        $testee
            ->expects($this->once())
            ->method('getCurrentLocale')
            ->willReturn($validLanguageCode);

        /*
         * Execute test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals($validLanguageCode, $result);
    }

    /**
     * Test Default Browser language is returned
     */
    public function testGetPaymentLocaleReturnsDefaultBrowserLanguage()
    {
        /*
         * Stubs
         */
        $validLanguageCode = 'en_US';

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'getPaymentLocale',
            [
                'getPaymentLocaleSetting',
                'browserLanguage',
            ]
        );

        /*
         * Expect to call getPaymentLocaleSettings and return Testee::SETTING_LOCALE_BY_BROWSER
         */
        $testee
            ->expects($this->once())
            ->method('getPaymentLocaleSetting')
            ->willReturn(Testee::SETTING_LOCALE_DETECT_BY_BROWSER);

        /*
         * Then expect to call browserLanguage to retrieve the browser language
         */
        $testee
            ->expects($this->once())
            ->method('browserLanguage')
            ->willReturn($validLanguageCode);

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals($validLanguageCode, $result);
    }

    /* -----------------------------------------------------------------
       getPaymentLocaleSetting Tests
       -------------------------------------------------------------- */

    /**
     * Test Wp Language option is returned as default value if options from
     * database is a falsy value.
     */
    public function testGetPaymentLocaleSettingsReturnWpLanguagePlaceholderAsDefaultValue()
    {
        /*
         * Stubs
         */
        $settingId = uniqid(mt_rand(0, 8));

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'getPaymentLocaleSetting',
            ['getSettingId']
        );

        /*
         * Expect to get the Setting Id
         */
        $testee
            ->expects($this->once())
            ->method('getSettingId')
            ->with(Testee::SETTING_NAME_PAYMENT_LOCALE)
            ->willReturn($settingId);

        /*
         * Then expect to retrieve the value from a call to `get_option` but
         * that value is a falsy value because of problem in retrieving the option.
         */
        Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with($settingId, Testee::SETTING_LOCALE_WP_LANGUAGE)
            ->andReturn(false);

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals(Testee::SETTING_LOCALE_WP_LANGUAGE, $result);
    }

    /* -----------------------------------------------------------------
       browserLanguage Tests
       -------------------------------------------------------------- */

    /**
     * Test browserLanguage
     */
    public function testBrowserLanguage()
    {
        /*
         * Stubs
         *
         * The httpAcceptedLanguages contains the normalize accepted languages strings.
         * Normalize to be compliant with Mollie accepted languages format.
         */
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en,en-US,de,de-DE';
        $httpAcceptedLanguages = ['en', 'en_US', 'de', 'de_DE'];
        $expectedLanguageCode = 'en_US';

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'browserLanguage',
            ['extractValidLanguageCode']
        );

        /*
         * Expect to call `extractValidLanguageCode
         */
        $testee
            ->expects($this->once())
            ->method('extractValidLanguageCode')
            ->with($httpAcceptedLanguages)
            ->willReturn($expectedLanguageCode);

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals($expectedLanguageCode, $result);
    }

    /**
     * Test Default language code is returned because the browser languages
     * doesn't include any allowed language.
     */
    public function testeeBrowserLanguageReturnDefaultLanguage()
    {
        /*
         * Stubs
         *
         * We don't care which is the value for the accepted languages
         * because we are testing the case where the browser languages doesn't include
         * any of the allowed language code.
         */
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'aa,aa_AA,ee,ee_EE';

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'browserLanguage',
            []
        );

        /*
         * Expect to apply filter over the Allowed Language Codes
         */
        expectFilterApplied(Testee::FILTER_ALLOWED_LANGUAGE_CODE_SETTING)
            ->once()
            ->with(Testee::ALLOWED_LANGUAGE_CODES)
            ->andReturn(Testee::ALLOWED_LANGUAGE_CODES);

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals(Testee::SETTING_LOCALE_DEFAULT_LANGUAGE, $result);
    }

    /**
     * Test Default language code is returned because no language was provided
     * by the request
     */
    public function testBrowserLanguageReturnDefaultLanguageBecauseNoLanguageProvidedByTheRequest()
    {
        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'browserLanguage',
            []
        );

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals(Testee::SETTING_LOCALE_DEFAULT_LANGUAGE, $result);
    }

    /**
     * Test Default language value is returned in case accept languages contains
     * falsy only values
     */
    public function testBrowserLanguageReturnDefaultLanguageBecauseRequestContainsOnlyFalsyValues()
    {
        /*
         * Stubs
         */
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = false;

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'browserLanguage',
            []
        );

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals(Testee::SETTING_LOCALE_DEFAULT_LANGUAGE, $result);
    }

    /* -----------------------------------------------------------------
       getCurrentLocale Tests
       -------------------------------------------------------------- */

    public function testGetCurrentLocale()
    {
        /*
         * Stubs
         */
        $locale = 'en_US';

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'getCurrentLocale',
            ['extractValidLanguageCode']
        );

        /*
         * Expect to get the current locale by calling Wp function `get_locale`
         */
        expect('get_locale')
            ->once()
            ->andReturn($locale);

        /*
         * Expect to call the extractValidLanguageCode
         */
        $testee
            ->expects($this->once())
            ->method('extractValidLanguageCode')
            ->with([$locale])
            ->willReturn($locale);

        /*
         * Execute Testee
         */
        $result = $testeeMethod->invoke($testee);

        self::assertEquals(Testee::SETTING_LOCALE_DEFAULT_LANGUAGE, $result);
    }

    /**
     * Test Invalid WordPress Locale will result in returning a valid locale
     */
    public function testGetCurrentLocaleWillReturnValidLocaleEvenIfWordPressInvalidCode()
    {
        /*
         * Stubs
         */
        $languageCode = 'en';
        $expectedValidLanguageCode = 'en_US';

        /*
         * Setup Testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'getCurrentLocale',
            ['extractValidLanguageCode']
        );

        /*
         * Expect to get the current languageCode by calling Wp function `get_locale`
         */
        expect('get_locale')
            ->once()
            ->andReturn($languageCode);

        /*
         * Expect to call the extractValidLanguageCode
         */
        $testee
            ->expects($this->once())
            ->method('extractValidLanguageCode')
            ->with([$languageCode])
            ->willReturn($expectedValidLanguageCode);

        /*
         * Execute Testee
         */
        $result = $testeeMethod->invoke($testee);

        // Only because `en_US` is the first in the list.
        self::assertEquals($expectedValidLanguageCode, $result);
    }

    /* -----------------------------------------------------------------
       extractValidLanguageCode Tests
       -------------------------------------------------------------- */

    /**
     * @dataProvider extractValidLanguageCodeDataProvider
     * @param array $languageCodes
     * @param $expectedResult
     * @throws ReflectionException
     * @throws \Brain\Monkey\Expectation\Exception\NotAllowedMethod
     */
    public function testExtractValidLanguageCode(array $languageCodes, $expectedResult)
    {
        /*
         * Setup testee
         */
        list($testee, $testeeMethod) = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            'extractValidLanguageCode',
            []
        );

        /*
         * Expect filter is applied to add or remove allowed language codes
         */
        expectFilterApplied(Testee::FILTER_ALLOWED_LANGUAGE_CODE_SETTING)
            ->once()
            ->andReturnFirstArg();

        /*
         * Execute Test
         */
        $result = $testeeMethod->invoke($testee, $languageCodes);

        self::assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function extractValidLanguageCodeDataProvider()
    {
        return [
            [['de'], 'de_DE'],
            [['de_DE'], 'de_DE'],
            [['de', 'de_DE'], 'de_DE'],
            [['de', 'fr_DE'], 'de_DE'],
            [['de', 'de_FR'], 'de_DE'],
            [['fr_DE', 'de'], 'de_DE'],
            [['de_FR', 'de'], 'de_DE'],
            [['cc_CD', 'cc', 'ff', 'aa_AA'], 'en_US'],
        ];
    }
}
