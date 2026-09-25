<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\WalletVisibility;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\CartLine;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;
use Mollie\WooCommerceTests\TestCase;

/**
 * Which wallets the Express Component offers (REQ-A1, A2, A7, H2, H3).
 *
 * Express checkout is not a gateway and has no switch of its own: it offers the wallets the plugin
 * already has as payment methods, and the merchant controls each one where they already do. A wallet
 * is shown only if its payment method exists in the plugin, the merchant enabled it, it is active at
 * Mollie, and that method's own "show the express button on the checkout" setting is on. HTTPS is
 * ExpressAvailability's check, made for the whole feature before any wallet. A wallet the plugin has no payment method for — Google Pay today — is never
 * offered, and appears with no code change on the day such a method is added.
 *
 * Given a cart, a wallet whose address comes from the checkout form is hidden while the form is
 * incomplete. In the Express Component every wallet takes it from the form, Apple Pay included (owner,
 * 2026-09-22); a wallet with its own address sheet would not wait, which is pinned with a row of its own.
 *
 * The wallet rows are the real ones in config/express.php. Whether a hidden wallet is reported as
 * false or left out of the map is the implementation's choice; only "shown" is pinned.
 *
 * @covers \Mollie\WooCommerce\Core\Express\WalletVisibility
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
     * @covers \Mollie\WooCommerce\Core\Express\WalletVisibility::buttons
     * @param array<int, string> $registered Wallets whose payment method exists in the plugin.
     * @param array<int, string> $enabled Wallets whose payment method the merchant enabled.
     * @param array<int, string> $active Wallets whose Mollie method is active.
     * @param array<int, string> $expressOn Wallets whose express button on the checkout is turned on.
     * @param array<int, string> $expectedShown
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
            $this->shop($isHttps, $registered, $enabled, $active, $expressOn)
        );

        $this->assertShownExactly($expectedShown, $buttons);
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
     * @covers \Mollie\WooCommerce\Core\Express\WalletVisibility::buttons
     */
    public function testShowsGooglePayOnceItsPaymentMethodExistsWithNoCodeChange(): void
    {
        $buttons = WalletVisibility::buttons(
            $this->settings(),
            $this->shop(true, self::WALLETS, self::WALLETS, self::WALLETS, self::WALLETS)
        );

        $this->assertShownExactly(self::WALLETS, $buttons);
    }

    /**
     * Scenario: every wallet waits for the checkout form, Apple Pay included
     *   Given Apple Pay and PayPal visible and a cart that ships
     *   When the buttons map is built while the shipping destination or rate is missing
     *   Then neither is shown: both take the shipping address from the checkout form
     *   And with the form complete, or nothing to ship, both are shown
     *
     * @dataProvider carts
     * @covers \Mollie\WooCommerce\Core\Express\WalletVisibility::buttons
     * @param array<int, string> $expectedShown
     */
    public function testHidesFormWalletsWhileTheCheckoutFormIsIncomplete(
        bool $needsShipping,
        bool $destinationComplete,
        bool $rateChosen,
        array $expectedShown
    ): void {

        $both = ['applepay', 'paypal'];
        $cart = new CartFacts(
            lines: [new CartLine(productId: 1, quantity: 1, isSubscription: false)],
            needsShipping: $needsShipping,
            shippingDestinationComplete: $destinationComplete,
            shippingRateChosen: $rateChosen
        );

        $buttons = WalletVisibility::buttons(
            $this->settings(),
            $this->shop(true, $both, $both, $both, $both),
            $cart
        );

        $this->assertShownExactly($expectedShown, $buttons);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: array<int, string>}>
     */
    public function carts(): array
    {
        $both = ['applepay', 'paypal'];

        return [
            'ships, destination incomplete' => [true, false, true, []],
            'ships, no rate chosen' => [true, true, false, []],
            'ships, neither' => [true, false, false, []],
            'ships, form complete' => [true, true, true, $both],
            'nothing to ship, form empty' => [false, false, false, $both],
        ];
    }

    /**
     * Scenario: a wallet with its own address sheet does not wait for the checkout form
     *   Given a wallets table where Apple Pay takes its address from its own sheet ('wallet')
     *   And PayPal takes it from the form
     *   When the buttons map is built for a cart that ships while the form is incomplete
     *   Then Apple Pay is shown and PayPal is not
     *
     * No real row uses 'wallet' since every wallet waits for the form (owner, 2026-09-22); this keeps
     * the rule working for the day a wallet is switched back by a data change.
     *
     * @covers \Mollie\WooCommerce\Core\Express\WalletVisibility::buttons
     */
    public function testKeepsAWalletWithItsOwnSheetWhileTheFormIsIncomplete(): void
    {
        $config = self::config();
        $wallets = $config['wallets'];
        $wallets['applepay']['addressFrom'] = 'wallet';
        $settings = new ExpressSettings(
            supportedSurfaces: $config['surfaces'],
            allowedModes: $config['allowedModes'],
            wallets: $wallets
        );
        $both = ['applepay', 'paypal'];
        $cart = new CartFacts(
            lines: [new CartLine(productId: 1, quantity: 1, isSubscription: false)],
            needsShipping: true,
            shippingDestinationComplete: false,
            shippingRateChosen: false
        );

        $buttons = WalletVisibility::buttons($settings, $this->shop(true, $both, $both, $both, $both), $cart);

        $this->assertShownExactly(['applepay'], $buttons);
    }

    /**
     * Scenario: the real wallets table takes every shipping address from the checkout form
     *   Given config/express.php
     *   Then every wallet row has addressFrom 'form'
     *
     * @covers \Mollie\WooCommerce\Core\Express\WalletVisibility::buttons
     */
    public function testEveryWalletTakesItsAddressFromTheCheckoutForm(): void
    {
        foreach (self::config()['wallets'] as $wallet => $row) {
            self::assertSame('form', $row['addressFrom'], "Wallet '{$wallet}' must wait for the checkout form.");
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

    private function settings(): ExpressSettings
    {
        $config = self::config();

        return new ExpressSettings(
            supportedSurfaces: $config['surfaces'],
            allowedModes: $config['allowedModes'],
            wallets: $config['wallets']
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
     * @return array{wallets: array<string, array{gatewayId: string, mollieMethod: string, checkoutSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>}
     */
    private static function config(): array
    {
        return require PROJECT_DIR . '/config/express.php';
    }
}
