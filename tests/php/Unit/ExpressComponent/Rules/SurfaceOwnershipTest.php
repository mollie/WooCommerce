<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\ExpressAvailability;
use Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether the Express Component owns a surface, so the legacy wallet buttons step aside there.
 * Only the checkout can be owned, and only for a cart that is ready when the page loads:
 * ownership is availability with that cart.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership
 */
class SurfaceOwnershipTest extends TestCase
{
    /**
     * Scenario: Express owns the checkout only when the cart's price is known when the page loads
     *   Given express settings, shop facts and the cart as it is when the page loads
     *   When ownership of a surface is asked
     *   Then the checkout is owned when the mode is allowed, the site is HTTPS, a wallet is visible
     *        and the cart is ready: nothing to ship, or a complete shipping address with a chosen rate,
     *        and no tax that waits for an address nobody gave
     *   And a cart that waits for the shipping form is not owned, so no placeholder is ever shown
     *   And the cart and the product page are never owned
     *   And the answer is the same as availability resolved with that cart
     *
     * @dataProvider ownershipCases
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership::owns
     * @param array<int, string> $wallets
     */
    public function testOwnsTheCheckoutOnlyWhenTheCartIsReadyAtPageLoad(
        string $surface,
        string $mode,
        bool $isHttps,
        array $wallets,
        CartFacts $cart,
        bool $expected
    ): void {

        $settings = $this->settings();
        $shop = $this->shop($mode, $isHttps, $wallets);

        $owns = SurfaceOwnership::owns($settings, $shop, $cart, $surface);

        self::assertSame($expected, $owns);
        self::assertSame(
            ExpressAvailability::resolve($settings, $shop, $cart, $surface)->status() === 'available',
            $owns,
            'Ownership must be availability with the cart, or the legacy buttons and the component can disagree.'
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: array<int, string>, 4: CartFacts, 5: bool}>
     */
    public function ownershipCases(): array
    {
        $wallets = ['applepay', 'paypal'];
        $nothingShips = self::cart(false, false);
        $shipsReady = self::cart(true, true);

        return [
            'the checkout, nothing to ship' => ['checkout', 'live', true, $wallets, $nothingShips, true],
            'the checkout, ships, address and rate known' => ['checkout', 'live', true, $wallets, $shipsReady, true],
            'the checkout, through one wallet' => ['checkout', 'live', true, ['paypal'], $nothingShips, true],
            'the checkout, ships, address not complete yet' => ['checkout', 'live', true, $wallets, self::cart(true, false), false],
            'the checkout, taxes follow an address nobody gave' => ['checkout', 'live', true, $wallets, self::cart(false, false, true, false), false],
            'the checkout, taxes follow the saved address' => ['checkout', 'live', true, $wallets, self::cart(false, false, true, true), true],
            'the checkout, an empty cart' => ['checkout', 'live', true, $wallets, new CartFacts([], false, false, false), false],
            'the checkout, a subscription in the cart' => ['checkout', 'live', true, $wallets, self::cart(false, false, false, false, true), false],
            'the cart, even with wallets on' => ['cart', 'live', true, $wallets, $nothingShips, false],
            'the product page, even with wallets on' => ['product', 'live', true, $wallets, $nothingShips, false],
            'the checkout, in test mode' => ['checkout', 'test', true, $wallets, $nothingShips, false],
            'the checkout, over plain HTTP' => ['checkout', 'live', false, $wallets, $nothingShips, false],
            'the checkout, with no wallet visible' => ['checkout', 'live', true, [], $nothingShips, false],
        ];
    }

    private static function cart(
        bool $needsShipping,
        bool $shippingComplete,
        bool $taxesFollowCustomerAddress = false,
        bool $customerAddressSaved = false,
        bool $isSubscription = false
    ): CartFacts {

        return new CartFacts(
            lines: [new CartLine(productId: 100, quantity: 1, isSubscription: $isSubscription)],
            needsShipping: $needsShipping,
            shippingDestinationComplete: $shippingComplete,
            shippingRateChosen: $shippingComplete,
            total: Money::fromMinorUnits(1000, 'EUR'),
            taxesFollowCustomerAddress: $taxesFollowCustomerAddress,
            customerAddressSaved: $customerAddressSaved
        );
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
     * @return array{wallets: array<string, array{gatewayId: string, mollieMethod: string, paidAs: string, checkoutSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>}
     */
    private static function config(): array
    {
        return require PROJECT_DIR . '/config/express.php';
    }
}
