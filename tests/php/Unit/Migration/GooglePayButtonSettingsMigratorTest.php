<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Migration;

use Mollie\WooCommerce\Activation\Migrations\GooglePayButtonSettingsMigrator;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * Google Pay's enabled switch doubled as its checkout switch up to 8.2.0; the update gives it a
 * switch per page and keeps what the merchant saw.
 */
class GooglePayButtonSettingsMigratorTest extends TestCase
{
    private const GOOGLE_PAY = 'mollie_wc_gateway_googlepay_settings';

    private const APPLE_PAY = 'mollie_wc_gateway_applepay_settings';

    private const PAYPAL = 'mollie_wc_gateway_paypal_settings';

    private const CHECKOUT = 'mollie_googlepay_button_enabled_checkout';

    private const CART = 'mollie_googlepay_button_enabled_cart';

    /**
     * @scenario An update from 8.2.0 keeps Google Pay where the merchant saw it and leaves the other wallets alone
     *   Given a store on 8.2.0 with Apple Pay and PayPal page switches stored
     *   And Google Pay enabled, disabled, or never saved
     *   When the Google Pay button settings migration runs
     *   Then the Google Pay checkout and cart switches are on where Google Pay is enabled, and a switch already stored is kept
     *   And nothing is written where it is disabled or never saved, so the switches stay never saved
     *   And the stored Apple Pay and PayPal switches are unchanged
     *   And the migration belongs to a version after 8.2.0 and no later than the plugin's, so the update ladder runs it
     *
     * @test
     * @dataProvider googlePayBefore
     * @param array<string, string>|null $googlePay null: the option was never saved
     * @param array{0: ?string, 1: ?string} $expected checkout switch, cart switch; null: not stored
     * @group REQ-A1
     */
    public function it_sets_the_google_pay_page_switches_from_its_enabled_switch(?array $googlePay, array $expected): void
    {
        // Given
        $store = [
            self::APPLE_PAY => [
                'enabled' => 'yes',
                'mollie_apple_pay_button_enabled_cart' => 'yes',
                'mollie_apple_pay_button_enabled_express_checkout' => 'no',
            ],
            self::PAYPAL => [
                'enabled' => 'yes',
                'mollie_paypal_button_enabled_cart' => 'no',
                'mollie_paypal_button_enabled_checkout' => 'yes',
            ],
        ];
        if ($googlePay !== null) {
            $store[self::GOOGLE_PAY] = $googlePay;
        }
        $before = $store;
        when('get_option')->alias(static function (string $name, $default = false) use (&$store) {
            return $store[$name] ?? $default;
        });
        when('update_option')->alias(static function (string $name, $value) use (&$store): bool {
            $store[$name] = $value;

            return true;
        });

        // When
        $migrator = new GooglePayButtonSettingsMigrator();
        $migrator->migrate();

        // Then
        $stored = (array) ($store[self::GOOGLE_PAY] ?? []);
        self::assertSame(
            $expected,
            [$stored[self::CHECKOUT] ?? null, $stored[self::CART] ?? null],
            'Google Pay page switches after the update: ' . json_encode($stored)
        );
        if (($googlePay['enabled'] ?? 'no') !== 'yes') {
            self::assertSame($before[self::GOOGLE_PAY] ?? null, $store[self::GOOGLE_PAY] ?? null, 'Nothing is written for Google Pay when it is not enabled.');
        }
        self::assertSame($googlePay['enabled'] ?? null, $stored['enabled'] ?? null, 'The enabled switch stays the on/off of the method.');
        self::assertSame($before[self::APPLE_PAY], $store[self::APPLE_PAY]);
        self::assertSame($before[self::PAYPAL], $store[self::PAYPAL]);
        self::assertTrue(
            version_compare($migrator->targetVersion(), '8.2.0', '>'),
            'Target ' . $migrator->targetVersion() . ' must come after 8.2.0.'
        );
        self::assertTrue(
            version_compare($migrator->targetVersion(), $this->pluginVersion(), '<='),
            'Target ' . $migrator->targetVersion() . ' must not come after the plugin version ' . $this->pluginVersion() . ', or the update ladder skips it.'
        );
    }

    private function pluginVersion(): string
    {
        $header = (string) file_get_contents(dirname(__DIR__, 4) . '/mollie-payments-for-woocommerce.php');
        self::assertSame(1, preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $header, $match), 'The plugin header names its version.');

        return $match[1];
    }

    /**
     * @return array<string, array{0: array<string, string>|null, 1: array{0: ?string, 1: ?string}}>
     */
    public function googlePayBefore(): array
    {
        return [
            'enabled' => [['enabled' => 'yes', 'title' => 'Google Pay'], ['yes', 'yes']],
            'enabled, cart already saved off' => [['enabled' => 'yes', self::CART => 'no'], ['yes', 'no']],
            'disabled' => [['enabled' => 'no', 'title' => 'Google Pay'], [null, null]],
            'never saved' => [null, [null, null]],
        ];
    }
}
