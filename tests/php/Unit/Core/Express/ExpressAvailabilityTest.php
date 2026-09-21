<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\ExpressAvailability;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\CartLine;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;
use Mollie\WooCommerceTests\TestCase;

/**
 * May the Express Component run here, for this cart, in this mode (REQ-A1, A2, H1, H2, H3, H4).
 *
 * Every other express spec asks this first, so the answer has one owner and one fixed, readable
 * order of checks: the surface is supported, the mode is allowed, the site is HTTPS, at least one
 * wallet is visible; then, given a cart, it is non-empty, holds no subscription, and — if it ships —
 * some visible wallet can be used with the checkout form as it is.
 *
 * Express has no switch of its own. A wallet is visible when the merchant turned on its payment
 * method's "show the express button on the checkout" setting, so "the merchant did not ask for it"
 * and "nothing is visible" are one answer: no_wallet_visible.
 *
 * A cart that ships is blocked, not unavailable, while every visible wallet still needs the checkout
 * form's address (PayPal, Google Pay) and the form is incomplete. A wallet with its own address sheet
 * (Apple Pay) does not wait for the form. The wallets, surfaces and allowed modes come from the real
 * config/express.php, so a change to that table is seen here.
 *
 * @covers \Mollie\WooCommerce\Core\Express\ExpressAvailability
 */
class ExpressAvailabilityTest extends TestCase
{
    private const ALL_WALLETS = ['applepay', 'paypal'];

    /**
     * Scenario: without a cart, the checkout is available only when every shop-level check holds
     *   Given express settings, shop facts and a surface, with no cart
     *   When availability is resolved
     *   Then it is available only on the checkout, in an allowed mode, over HTTPS and with at least
     *        one wallet visible
     *   And otherwise it is unavailable with the reason of the first check that failed
     *
     * @dataProvider shopLevelCases
     * @covers \Mollie\WooCommerce\Core\Express\ExpressAvailability::resolve
     * @param array<int, string> $wallets Wallets that are visible: method enabled, active, express button on.
     */
    public function testResolvesAvailabilityWithoutACart(
        string $surface,
        string $mode,
        bool $isHttps,
        array $wallets,
        string $expectedStatus,
        ?string $expectedReason
    ): void {

        $result = ExpressAvailability::resolve(
            $this->settings(),
            $this->shop($mode, $isHttps, $wallets),
            null,
            $surface
        );

        self::assertSame($expectedStatus, $result->status());
        self::assertSame($expectedReason, $result->reason());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: array<int, string>, 4: string, 5: ?string}>
     */
    public function shopLevelCases(): array
    {
        return [
            'everything holds on the checkout' => ['checkout', 'live', true, self::ALL_WALLETS, 'available', null],
            'one wallet is enough' => ['checkout', 'live', true, ['paypal'], 'available', null],
            'the cart is not a v1 surface' => ['cart', 'live', true, self::ALL_WALLETS, 'unavailable', 'surface_not_supported'],
            'the product page is not a v1 surface' => ['product', 'live', true, self::ALL_WALLETS, 'unavailable', 'surface_not_supported'],
            'test mode is not allowed' => ['checkout', 'test', true, self::ALL_WALLETS, 'unavailable', 'mode_not_allowed'],
            'the site is not HTTPS' => ['checkout', 'live', false, self::ALL_WALLETS, 'unavailable', 'not_https'],
            // No wallet has its express button turned on (or exists, is enabled, is active): the merchant asked for nothing.
            'no wallet is visible' => ['checkout', 'live', true, [], 'unavailable', 'no_wallet_visible'],
            // The order is fixed: each row fails every later check too, and must report only the first.
            'an unsupported surface is reported before the mode' => ['cart', 'test', false, [], 'unavailable', 'surface_not_supported'],
            'the mode is reported before HTTPS' => ['checkout', 'test', false, [], 'unavailable', 'mode_not_allowed'],
            'HTTPS is reported before the wallets' => ['checkout', 'live', false, [], 'unavailable', 'not_https'],
        ];
    }

    /**
     * Scenario: a cart that express checkout cannot pay is refused
     *   Given the checkout is otherwise available
     *   When availability is resolved for an empty cart, or a cart holding a subscription product
     *   Then it is unavailable with cart_empty, or subscription_in_cart
     *   And a shop-level reason still wins over a cart-level one
     *
     * @dataProvider unpayableCarts
     * @covers \Mollie\WooCommerce\Core\Express\ExpressAvailability::resolve
     * @param array<int, bool> $lines One entry per cart line: whether it is a subscription.
     * @param array<int, string> $wallets
     */
    public function testRefusesACartThatCannotBePaidByExpress(
        array $lines,
        array $wallets,
        string $expectedReason
    ): void {

        $result = ExpressAvailability::resolve(
            $this->settings(),
            $this->shop('live', true, $wallets),
            $this->cart($lines, false, false, false),
            'checkout'
        );

        self::assertSame('unavailable', $result->status());
        self::assertSame($expectedReason, $result->reason());
    }

    /**
     * @return array<string, array{0: array<int, bool>, 1: array<int, string>, 2: string}>
     */
    public function unpayableCarts(): array
    {
        return [
            'an empty cart' => [[], self::ALL_WALLETS, 'cart_empty'],
            'a subscription product' => [[true], self::ALL_WALLETS, 'subscription_in_cart'],
            'a subscription next to a normal product' => [[false, true], self::ALL_WALLETS, 'subscription_in_cart'],
            'no wallet is visible and the cart is empty' => [[], [], 'no_wallet_visible'],
        ];
    }

    /**
     * Scenario: a cart that ships waits for the checkout form only where the wallet needs it
     *   Given the checkout is otherwise available and the cart holds a normal product
     *   When availability is resolved while the shipping destination or rate is missing
     *   Then it is blocked with shipping_incomplete, not unavailable, if every visible wallet takes
     *        its address from the checkout form
     *   And it is available once the destination is complete and a rate is chosen
     *   And it is available whatever the form says if a visible wallet has its own address sheet
     *   And a cart that needs no shipping is available whatever the form says
     *
     * @dataProvider shippingCases
     * @covers \Mollie\WooCommerce\Core\Express\ExpressAvailability::resolve
     * @param array<int, bool> $lines
     * @param array<int, string> $wallets
     */
    public function testBlocksAShippableCartUntilTheFormIsCompleteWhereTheWalletNeedsIt(
        array $lines,
        array $wallets,
        bool $needsShipping,
        bool $destinationComplete,
        bool $rateChosen,
        string $expectedStatus,
        ?string $expectedReason
    ): void {

        $result = ExpressAvailability::resolve(
            $this->settings(),
            $this->shop('live', true, $wallets),
            $this->cart($lines, $needsShipping, $destinationComplete, $rateChosen),
            'checkout'
        );

        self::assertSame($expectedStatus, $result->status());
        self::assertSame($expectedReason, $result->reason());
    }

    /**
     * @return array<string, array{0: array<int, bool>, 1: array<int, string>, 2: bool, 3: bool, 4: bool, 5: string, 6: ?string}>
     */
    public function shippingCases(): array
    {
        return [
            'PayPal only, ships, destination incomplete' => [[false], ['paypal'], true, false, true, 'blocked', 'shipping_incomplete'],
            'PayPal only, ships, no rate chosen' => [[false], ['paypal'], true, true, false, 'blocked', 'shipping_incomplete'],
            'PayPal only, ships, neither' => [[false], ['paypal'], true, false, false, 'blocked', 'shipping_incomplete'],
            'PayPal only, ships, destination and rate complete' => [[false], ['paypal'], true, true, true, 'available', null],
            'PayPal only, nothing to ship, form empty' => [[false], ['paypal'], false, false, false, 'available', null],
            // Apple Pay takes the address from its own sheet, so the checkout form is not needed.
            'Apple Pay only, ships, form empty' => [[false], ['applepay'], true, false, false, 'available', null],
            'both wallets, ships, form empty: Apple Pay is enough' => [[false], self::ALL_WALLETS, true, false, false, 'available', null],
            // Filling in the form cannot fix a subscription, so it is unavailable, not blocked.
            'PayPal only, ships incomplete, with a subscription' => [[true], ['paypal'], true, false, false, 'unavailable', 'subscription_in_cart'],
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
     * @param array<int, bool> $lines One entry per line: whether it is a subscription.
     */
    private function cart(array $lines, bool $needsShipping, bool $destinationComplete, bool $rateChosen): CartFacts
    {
        $cartLines = [];
        foreach ($lines as $index => $isSubscription) {
            $cartLines[] = new CartLine(productId: 100 + $index, quantity: 1, isSubscription: $isSubscription);
        }

        return new CartFacts(
            lines: $cartLines,
            needsShipping: $needsShipping,
            shippingDestinationComplete: $destinationComplete,
            shippingRateChosen: $rateChosen
        );
    }

    /**
     * @return array{wallets: array<string, array{gatewayId: string, mollieMethod: string, needsHttps: bool, checkoutSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>}
     */
    private static function config(): array
    {
        return require PROJECT_DIR . '/config/express.php';
    }
}
