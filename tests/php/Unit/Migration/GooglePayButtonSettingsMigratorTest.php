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
     *   Then the Google Pay checkout switch is on and its cart switch off where Google Pay is enabled
     *   And both are off where it is disabled or never saved
     *   And the stored Apple Pay and PayPal switches are unchanged
     *   And the migration belongs to a version after 8.2.0, so the update ladder runs it
     *
     * @test
     * @dataProvider googlePayBefore
     * @param array<string, string>|null $googlePay null: the option was never saved
     * @param array{0: string, 1: string} $expected checkout switch, cart switch
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
            [$stored[self::CHECKOUT] ?? 'no', $stored[self::CART] ?? 'no'],
            'Google Pay page switches after the update: ' . json_encode($stored)
        );
        self::assertSame($googlePay['enabled'] ?? null, $stored['enabled'] ?? null, 'The enabled switch stays the on/off of the method.');
        self::assertSame($before[self::APPLE_PAY], $store[self::APPLE_PAY]);
        self::assertSame($before[self::PAYPAL], $store[self::PAYPAL]);
        self::assertTrue(
            version_compare($migrator->targetVersion(), '8.2.0', '>'),
            'Target ' . $migrator->targetVersion() . ' must come after 8.2.0.'
        );
    }

    /**
     * @return array<string, array{0: array<string, string>|null, 1: array{0: string, 1: string}}>
     */
    public function googlePayBefore(): array
    {
        return [
            'enabled' => [['enabled' => 'yes', 'title' => 'Google Pay'], ['yes', 'no']],
            'disabled' => [['enabled' => 'no', 'title' => 'Google Pay'], ['no', 'no']],
            'never saved' => [null, ['no', 'no']],
        ];
    }
}
