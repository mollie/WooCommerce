<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Migration;

use Mollie\WooCommerce\Activation\Migrations\PaymentMethodSettingsMigrator;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class PaymentMethodSettingsMigratorTest extends TestCase
{
    private const OPTION_NAME = 'mollie_wc_gateway_ideal_settings';

    private function makeFakeWpdb(array $optionNames): object
    {
        return new class($optionNames) {
            public string $options = 'wp_options';
            private array $names;

            public function __construct(array $names)
            {
                $this->names = $names;
            }

            public function get_col(string $query): array
            {
                return $this->names;
            }
        };
    }

    private function runMigrate(array $input): array
    {
        $GLOBALS['wpdb'] = $this->makeFakeWpdb([self::OPTION_NAME]);

        $captured = null;
        when('get_option')->justReturn($input);
        expect('update_option')
            ->once()
            ->andReturnUsing(static function (string $name, array $settings) use (&$captured): bool {
                $captured = $settings;
                return true;
            });

        (new PaymentMethodSettingsMigrator())->migrate();

        return $captured ?? [];
    }

    // T1: use_api_title=yes, title set → title cleared, key removed
    public function testMigrateTitleWhenUseApiTitleYesClears(): void
    {
        $result = $this->runMigrate(['use_api_title' => 'yes', 'title' => 'My Title']);

        self::assertArrayNotHasKey('use_api_title', $result);
        self::assertArrayNotHasKey('title', $result);
    }

    // T2: use_api_title key absent → title cleared
    public function testMigrateTitleWhenKeyAbsentClears(): void
    {
        $result = $this->runMigrate(['title' => 'My Title']);

        self::assertArrayNotHasKey('use_api_title', $result);
        self::assertArrayNotHasKey('title', $result);
    }

    // T3: use_api_title=no, non-empty title → title preserved, key removed
    public function testMigrateTitleWhenUseApiTitleNoPreservesTitle(): void
    {
        $result = $this->runMigrate(['use_api_title' => 'no', 'title' => 'My Title']);

        self::assertArrayNotHasKey('use_api_title', $result);
        self::assertSame('My Title', $result['title']);
    }

    // T4: use_api_title=no, empty title → title cleared
    public function testMigrateTitleWhenUseApiTitleNoButEmptyTitleClears(): void
    {
        $result = $this->runMigrate(['use_api_title' => 'no', 'title' => '']);

        self::assertArrayNotHasKey('use_api_title', $result);
        self::assertArrayNotHasKey('title', $result);
    }

    // T5: enable_custom_logo=yes + valid iconFileUrl → url/path preserved, key removed
    public function testMigrateLogoWhenEnabledWithUrlPreservesLogo(): void
    {
        $result = $this->runMigrate([
            'enable_custom_logo' => 'yes',
            'iconFileUrl' => 'https://example.com/logo.svg',
            'iconFilePath' => '/var/www/logo.svg',
        ]);

        self::assertArrayNotHasKey('enable_custom_logo', $result);
        self::assertSame('https://example.com/logo.svg', $result['iconFileUrl']);
        self::assertSame('/var/www/logo.svg', $result['iconFilePath']);
    }

    // T6: enable_custom_logo=no → url/path cleared, key removed
    public function testMigrateLogoWhenDisabledClearsLogo(): void
    {
        $result = $this->runMigrate([
            'enable_custom_logo' => 'no',
            'iconFileUrl' => 'https://example.com/logo.svg',
            'iconFilePath' => '/var/www/logo.svg',
        ]);

        self::assertArrayNotHasKey('enable_custom_logo', $result);
        self::assertArrayNotHasKey('iconFileUrl', $result);
        self::assertArrayNotHasKey('iconFilePath', $result);
    }

    // T7: enable_custom_logo=yes, empty iconFileUrl → url/path cleared
    public function testMigrateLogoWhenEnabledButEmptyUrlClearsLogo(): void
    {
        $result = $this->runMigrate([
            'enable_custom_logo' => 'yes',
            'iconFileUrl' => '',
            'iconFilePath' => '/var/www/logo.svg',
        ]);

        self::assertArrayNotHasKey('enable_custom_logo', $result);
        self::assertArrayNotHasKey('iconFileUrl', $result);
        self::assertArrayNotHasKey('iconFilePath', $result);
    }

    // T8: display_logo=no → value unchanged
    public function testMigrateDoesNotTouchDisplayLogo(): void
    {
        $result = $this->runMigrate(['display_logo' => 'no', 'enable_custom_logo' => 'no']);

        self::assertSame('no', $result['display_logo']);
        self::assertArrayNotHasKey('enable_custom_logo', $result);
    }

    // T9: get_option returns false → update_option not called
    public function testMigrateSkipsOptionWhenGetOptionReturnsFalse(): void
    {
        $GLOBALS['wpdb'] = $this->makeFakeWpdb([self::OPTION_NAME]);

        expect('get_option')
            ->once()
            ->with(self::OPTION_NAME, false)
            ->andReturn(false);
        expect('update_option')->never();

        (new PaymentMethodSettingsMigrator())->migrate();

        self::assertTrue(true);
    }

    // T10: targetVersion() returns '8.1.7'
    public function testTargetVersionIsEightOneSeven(): void
    {
        self::assertSame('8.1.7', (new PaymentMethodSettingsMigrator())->targetVersion());
    }
}
