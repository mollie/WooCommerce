<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility;
use Mollie\WooCommerceTests\TestCase;

/**
 * Which wallets the Express Component offers.
 * Uses the real wallet rows in config/express.php; only "shown" is asserted.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility
 */
class WalletVisibilityTest extends TestCase
{
    private const WALLETS = ['applepay', 'paypal', 'googlepay'];

    /**
     * Scenario: a wallet is shown only when its method exists, is enabled, is active and has its express button on
     *   Given the wallets table and the payment methods the plugin has, the merchant enabled, Mollie
     *         reports active, and the merchant turned the express button on for at the checkout
     *   When the buttons map is built
     *   Then exactly the wallets passing every check are shown
     *   And Google Pay is hidden while the plugin has no Google Pay payment method
     *
     * @dataProvider visibilityCases
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility::buttons
     * @param array<int, string> $registered Wallets whose payment method exists in the plugin.
     * @param array<int, string> $enabled Wallets whose payment method the merchant enabled.
     * @param array<int, string> $active Wallets whose Mollie method is active.
     * @param array<int, string> $expressOn Wallets whose express button on the checkout is turned on.
     * @param array<int, string> $expectedShown
     * @group REQ-A7
     */
    public function testShowsOnlyWalletsWhoseMethodExistsIsEnabledIsActiveAndHasExpressOn(
        bool $isHttps,
        array $registered,
        array $enabled,
        array $active,
        array $expressOn,
        array $expectedShown
    ): void {

        $buttons = WalletVisibility::buttons(
            $this->settings(),
            $this->shop($isHttps, $registered, $enabled, $active, $expressOn),
            'checkout'
        );

        $this->assertShownExactly($expectedShown, $buttons);
    }

    /**
     * Scenario: a wallet whose payment method carries a surcharge is not shown
     *   Given both wallets pass every other check
     *   And the merchant set a surcharge on some of their payment methods
     *   When the buttons map is built
     *   Then only the wallets without a surcharge are shown: one session has one amount for every wallet
     *
     * @dataProvider surcharges
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility::buttons
     * @param array<int, string> $surcharged Wallets whose payment method carries a surcharge.
     * @param array<int, string> $expectedShown
     */
    public function testHidesAWalletWhosePaymentMethodCarriesASurcharge(array $surcharged, array $expectedShown): void
    {
        $both = ['applepay', 'paypal'];
        $shop = new ShopFacts(
            mode: 'live',
            isHttps: true,
            registeredGatewayIds: $this->column($both, 'gatewayId'),
            enabledGatewayIds: $this->column($both, 'gatewayId'),
            activeMollieMethods: $this->column($both, 'mollieMethod'),
            expressCheckoutGatewayIds: $this->column($both, 'gatewayId'),
            surchargedGatewayIds: $this->column($surcharged, 'gatewayId')
        );

        $this->assertShownExactly($expectedShown, WalletVisibility::buttons($this->settings(), $shop, 'checkout'));
    }

    /**
     * @return array<string, array{0: array<int, string>, 1: array<int, string>}>
     */
    public function surcharges(): array
    {
        return [
            'no surcharge anywhere' => [[], ['applepay', 'paypal']],
            'PayPal carries a surcharge' => [['paypal'], ['applepay']],
            'Apple Pay carries a surcharge' => [['applepay'], ['paypal']],
            'both carry a surcharge' => [['applepay', 'paypal'], []],
        ];
    }

    /**
     * @return array<string, array{0: bool, 1: array<int, string>, 2: array<int, string>, 3: array<int, string>, 4: array<int, string>, 5: array<int, string>}>
     */
    public function visibilityCases(): array
    {
        $both = ['applepay', 'paypal'];

        return [
            'both enabled, active and with express on' => [true, $both, $both, $both, $both, $both],
            'the merchant switched Apple Pay off' => [true, $both, ['paypal'], $both, $both, ['paypal']],
            'the merchant switched PayPal off' => [true, $both, ['applepay'], $both, $both, ['applepay']],
            'PayPal is not active at Mollie' => [true, $both, $both, ['applepay'], $both, ['applepay']],
            'Apple Pay is not active at Mollie' => [true, $both, $both, ['paypal'], $both, ['paypal']],
            // HTTPS is ExpressAvailability's question, asked before any wallet: it hides no wallet here.
            'plain HTTP is not decided per wallet' => [false, $both, $both, $both, $both, $both],
            // Enabled, active and even express-on are not enough: the plugin must have the payment method.
            'Google Pay has no payment method in the plugin' => [
                true,
                $both,
                self::WALLETS,
                self::WALLETS,
                self::WALLETS,
                $both,
            ],
            'nothing enabled' => [true, $both, [], $both, $both, []],
            // The merchant decides per method whether its express button is wanted at the checkout.
            'Apple Pay express button is off' => [true, $both, $both, $both, ['paypal'], ['paypal']],
            'PayPal express button is off' => [true, $both, $both, $both, ['applepay'], ['applepay']],
            'no express button is turned on' => [true, $both, $both, $both, [], []],
        ];
    }

    /**
     * Scenario: Google Pay appears the day its payment method exists, with no code change
     *   Given a Google Pay payment method registered, enabled and active, with its express button on
     *   When the buttons map is built by the same function
     *   Then Google Pay is shown next to Apple Pay and PayPal
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility::buttons
     */
    public function testShowsGooglePayOnceItsPaymentMethodExistsWithNoCodeChange(): void
    {
        $buttons = WalletVisibility::buttons(
            $this->settings(),
            $this->shop(true, self::WALLETS, self::WALLETS, self::WALLETS, self::WALLETS),
            'checkout'
        );

        $this->assertShownExactly(self::WALLETS, $buttons);
    }

    /**
     * Scenario: the real wallets table takes every shipping address from the wallet
     *   Given config/express.php
     *   Then every wallet row has addressFrom 'wallet', so a cart that ships never waits for the checkout form
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility::buttons
     * @group REQ-H2
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function testEveryWalletTakesItsAddressFromTheWallet(): void
    {
        foreach (self::config()['wallets'] as $wallet => $row) {
            self::assertSame('wallet', $row['addressFrom'], "Wallet '{$wallet}' must take its address from the wallet.");
        }
    }

    /**
     * @param array<int, string> $expectedShown
     * @param array<string, bool> $buttons
     */
    private function assertShownExactly(array $expectedShown, array $buttons): void
    {
        foreach (array_keys($buttons) as $wallet) {
            self::assertContains($wallet, self::WALLETS, "'{$wallet}' is not a row of the wallets table.");
        }
        foreach (self::WALLETS as $wallet) {
            self::assertSame(
                in_array($wallet, $expectedShown, true),
                ($buttons[$wallet] ?? false) === true,
                sprintf("Wallet '%s' visibility is wrong in %s.", $wallet, (string) json_encode($buttons))
            );
        }
    }

    /**
     * @param string|null $addressFrom Sets every wallet's addressFrom: no real row uses 'form' any more,
     *        the switch is what is under test.
     */
    private function settings(?string $addressFrom = null): ExpressSettings
    {
        $config = self::config();
        $wallets = $config['wallets'];
        if ($addressFrom !== null) {
            foreach ($wallets as $wallet => $row) {
                $wallets[$wallet]['addressFrom'] = $addressFrom;
            }
        }

        return new ExpressSettings(
            supportedSurfaces: $config['surfaces'],
            allowedModes: $config['allowedModes'],
            wallets: $wallets
        );
    }

    /**
     * Wallet names are translated to the gateway ids and Mollie method ids of their config rows,
     * so a renamed id in the table is caught here rather than in production.
     *
     * @param array<int, string> $registered
     * @param array<int, string> $enabled
     * @param array<int, string> $active
     * @param array<int, string> $expressOn
     */
    private function shop(bool $isHttps, array $registered, array $enabled, array $active, array $expressOn): ShopFacts
    {
        return new ShopFacts(
            mode: 'live',
            isHttps: $isHttps,
            registeredGatewayIds: $this->column($registered, 'gatewayId'),
            enabledGatewayIds: $this->column($enabled, 'gatewayId'),
            activeMollieMethods: $this->column($active, 'mollieMethod'),
            expressCheckoutGatewayIds: $this->column($expressOn, 'gatewayId')
        );
    }

    /**
     * @param array<int, string> $wallets
     * @return array<int, string>
     */
    private function column(array $wallets, string $field): array
    {
        $rows = self::config()['wallets'];
        $values = [];
        foreach ($wallets as $wallet) {
            self::assertArrayHasKey($wallet, $rows, "config/express.php has no '{$wallet}' wallet row.");
            $values[] = $rows[$wallet][$field];
        }

        return $values;
    }

    /**
     * @return array{wallets: array<string, array{gatewayId: string, mollieMethod: string, paidAs?: string, paidWallet?: string, checkoutSetting: string, cartSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>}
     */
    private static function config(): array
    {
        return require PROJECT_DIR . '/config/express.php';
    }
}
