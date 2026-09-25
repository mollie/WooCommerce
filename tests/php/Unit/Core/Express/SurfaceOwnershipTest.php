<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\ExpressAvailability;
use Mollie\WooCommerce\Core\Express\SurfaceOwnership;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;
use Mollie\WooCommerceTests\TestCase;

/**
 * The one mutual-exclusion rule (REQ-A3, A4, A6): does the Express Component own this surface?
 *
 * When it does, the legacy Apple Pay and PayPal buttons step aside there; when it does not — no
 * wallet has its express button turned on, or it is on but Express cannot run — they keep exactly
 * what the merchant configured, so a store is never left with no express option. v1 owns the
 * checkout only: the cart and the product page are not supported surfaces.
 *
 * Ownership is availability without a cart. That is pinned too, so the two can never drift.
 *
 * @covers \Mollie\WooCommerce\Core\Express\SurfaceOwnership
 */
class SurfaceOwnershipTest extends TestCase
{
    /**
     * Scenario: Express owns the checkout only, and only when it can actually run there
     *   Given express settings and shop facts
     *   When ownership of a surface is asked
     *   Then the checkout is owned when the mode is allowed, the site is HTTPS and a wallet is visible
     *   And the cart and the product page are never owned
     *   And the answer is the same as availability resolved without a cart
     *
     * @dataProvider ownershipCases
     * @covers \Mollie\WooCommerce\Core\Express\SurfaceOwnership::owns
     * @param array<int, string> $wallets
     */
    public function testOwnsOnlyTheCheckoutAndOnlyWhenExpressCanRunThere(
        string $surface,
        string $mode,
        bool $isHttps,
        array $wallets,
        bool $expected
    ): void {

        $settings = $this->settings();
        $shop = $this->shop($mode, $isHttps, $wallets);

        $owns = SurfaceOwnership::owns($settings, $shop, $surface);

        self::assertSame($expected, $owns);
        self::assertSame(
            ExpressAvailability::resolve($settings, $shop, null, $surface)->status() === 'available',
            $owns,
            'Ownership must be availability without a cart, or the legacy buttons and the component can disagree.'
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: array<int, string>, 4: bool}>
     */
    public function ownershipCases(): array
    {
        $wallets = ['applepay', 'paypal'];

        return [
            'the checkout, able to run' => ['checkout', 'live', true, $wallets, true],
            'the checkout, through one wallet' => ['checkout', 'live', true, ['paypal'], true],
            'the cart, even with wallets on' => ['cart', 'live', true, $wallets, false],
            'the product page, even with wallets on' => ['product', 'live', true, $wallets, false],
            'the checkout, in test mode' => ['checkout', 'test', true, $wallets, false],
            'the checkout, over plain HTTP' => ['checkout', 'live', false, $wallets, false],
            'the checkout, with no wallet visible' => ['checkout', 'live', true, [], false],
        ];
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
     * @param array<int, string> $wallets Wallets that are visible: registered, enabled, active and with
     *        their express button turned on at the checkout.
     */
    private function shop(string $mode, bool $isHttps, array $wallets): ShopFacts
    {
        $rows = self::config()['wallets'];
        $gatewayIds = [];
        $mollieMethods = [];
        foreach ($wallets as $wallet) {
            self::assertArrayHasKey($wallet, $rows, "config/express.php has no '{$wallet}' wallet row.");
            $gatewayIds[] = $rows[$wallet]['gatewayId'];
            $mollieMethods[] = $rows[$wallet]['mollieMethod'];
        }

        return new ShopFacts(
            mode: $mode,
            isHttps: $isHttps,
            registeredGatewayIds: $gatewayIds,
            enabledGatewayIds: $gatewayIds,
            activeMollieMethods: $mollieMethods,
            expressCheckoutGatewayIds: $gatewayIds
        );
    }

    /**
     * @return array{wallets: array<string, array{gatewayId: string, mollieMethod: string, checkoutSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>}
     */
    private static function config(): array
    {
        return require PROJECT_DIR . '/config/express.php';
    }
}
