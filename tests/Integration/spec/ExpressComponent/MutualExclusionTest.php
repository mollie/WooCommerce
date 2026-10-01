<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\PaymentMethods\Applepay;
use Mollie\WooCommerce\PaymentMethods\Paypal;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;

/**
 * The Express Component and the legacy Apple Pay / PayPal express buttons never share a surface
 * (REQ-A1 to A6).
 *
 * Two express buttons on one checkout can mean two orders and two charges: the flows create orders
 * by different routes and no lock or idempotency key spans them. Express has no switch of its own.
 * It takes over the checkout where the merchant already asked for an express button there, through a
 * wallet's own payment method settings, and only when it can run. Then the legacy buttons step aside
 * on the checkout page, and only there. When no wallet has its express button on, or Express cannot
 * run (test mode, plain HTTP, no wallet), the legacy buttons keep exactly what the merchant
 * configured. The cart block, the classic pages and the product page are never touched.
 *
 * Everything real: the gateway settings, the fact builder, the global helper and the two legacy
 * methods. Only Mollie is faked, by the harness, which also provides a registered and active Apple
 * Pay and PayPal.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressMutualExclusion
 */
class MutualExclusionTest extends ExpressFlowTestCase
{
    private const PAGES = ['cart', 'checkout', 'other'];

    private const APPLE_PAY_EXPRESS = 'mollie_apple_pay_button_enabled_express_checkout';

    private const PAYPAL_CHECKOUT = 'mollie_paypal_button_enabled_checkout';

    private const PAYPAL_CART = 'mollie_paypal_button_enabled_cart';

    /**
     * What each legacy method returns on each page when both checkout buttons and the PayPal cart
     * button are configured on: Apple Pay's one flag is not page-aware, PayPal's is.
     */
    private const AS_CONFIGURED = [
        'cart' => ['applepay' => true, 'paypal' => true],
        'checkout' => ['applepay' => true, 'paypal' => true],
        'other' => ['applepay' => true, 'paypal' => false],
    ];

    /**
     * @var array<int, array{0: string, 1: callable}>
     */
    private array $pageFilters = [];

    /**
     * @var array<int, array{0: string, 1: callable}>
     */
    private array $httpsFilters = [];

    public function tearDown(): void
    {
        $this->removeFilters($this->pageFilters);
        $this->removeFilters($this->httpsFilters);

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Off unless a wallet's own express button setting is on
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: Express does nothing until a wallet has its express button turned on at the checkout
     *   Given a live, HTTPS shop with Apple Pay and PayPal enabled and active
     *   And neither method's express button on the checkout turned on, or the setting never saved
     *   And PayPal's cart button on, which is a different setting
     *   When the plugin is asked whether Express owns a surface
     *   Then it owns none
     *   And the legacy methods return exactly what is configured
     *
     * @test
     * @dataProvider noExpressButtonOnTheCheckout
     * @param string|null $applePay null removes the setting, as if it had never been saved
     * @param string|null $payPal null removes the setting, as if it had never been saved
     */
    public function it_keeps_express_off_when_no_wallet_has_its_checkout_button_on(?string $applePay, ?string $payPal): void
    {
        // The site under test keeps the developer's own settings, so "never saved" means removing them.
        $this->setWalletSettings('applepay', ['enabled' => 'yes', self::APPLE_PAY_EXPRESS => $applePay]);
        $this->setWalletSettings('paypal', ['enabled' => 'yes', self::PAYPAL_CART => 'yes', self::PAYPAL_CHECKOUT => $payPal]);
        $this->useHttps(true);
        $this->bootExpress();

        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('checkout'), 'Express must be off.');
        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('cart'));
        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('product'));
        $this->assertSame(
            [
                'cart' => ['applepay' => false, 'paypal' => true],
                'checkout' => ['applepay' => false, 'paypal' => false],
                'other' => ['applepay' => false, 'paypal' => false],
            ],
            $this->legacyValues(),
            'With Express off, the legacy methods keep what the merchant configured.'
        );
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function noExpressButtonOnTheCheckout(): array
    {
        return [
            'both switched off' => ['no', 'no'],
            'never saved' => [null, null],
        ];
    }

    /**
     * Scenario: each wallet counts through its own setting, and only that one
     *   Given a live, HTTPS shop with Apple Pay and PayPal enabled and active
     *   And PayPal's cart button on
     *   When the Apple Pay express button and the PayPal checkout button are each on or off
     *   Then Express owns the checkout when at least one of them is on
     *   And PayPal's cart setting alone does not make it own anything
     *
     * This pins the setting each wallet row reads, and that a wallet counts alone.
     *
     * @test
     * @dataProvider checkoutButtonSettings
     */
    public function it_owns_the_checkout_when_a_wallet_has_its_checkout_button_on(string $applePay, string $payPal, bool $expected): void
    {
        $this->setGatewaySettingsForTest('applepay', ['enabled' => 'yes', self::APPLE_PAY_EXPRESS => $applePay]);
        $this->setGatewaySettingsForTest('paypal', ['enabled' => 'yes', self::PAYPAL_CART => 'yes', self::PAYPAL_CHECKOUT => $payPal]);
        $this->useHttps(true);
        $this->bootExpress();

        $this->assertSame($expected, mollieWooCommerceExpressOwnsSurface('checkout'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public function checkoutButtonSettings(): array
    {
        return [
            'both on' => ['yes', 'yes', true],
            'Apple Pay alone' => ['yes', 'no', true],
            'PayPal alone' => ['no', 'yes', true],
            'neither, whatever the PayPal cart setting says' => ['no', 'no', false],
        ];
    }

    /**
     * Scenario: the cart and the product page are not v1 surfaces, whatever is configured
     *   Given a live, HTTPS shop where Express can run
     *   When the plugin is asked whether Express owns each surface
     *   Then it owns the checkout only
     *
     * @test
     */
    public function it_never_owns_the_cart_or_the_product_page(): void
    {
        $this->configureLegacyButtons();
        $this->useHttps(true);
        $this->bootExpress();

        $this->assertTrue(mollieWooCommerceExpressOwnsSurface('checkout'));
        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('cart'));
        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('product'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mutual exclusion
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: when Express owns the checkout, the legacy buttons step aside there and only there
     *   Given a live, HTTPS shop with Apple Pay and PayPal enabled and active
     *   And the Apple Pay express flag and both PayPal page settings on
     *   When each legacy method is asked on the cart, the checkout and another page
     *   Then both return false on the checkout
     *   And both return their configured value on the cart
     *   And Apple Pay's non-page-aware flag still decides everywhere else
     *
     * @test
     */
    public function it_hides_legacy_buttons_on_checkout_only_when_express_owns_it(): void
    {
        $this->configureLegacyButtons();
        $this->useHttps(true);
        $this->bootExpress();

        $this->assertTrue(mollieWooCommerceExpressOwnsSurface('checkout'), 'Precondition: Express can run here.');
        $this->assertSame(
            [
                'cart' => ['applepay' => true, 'paypal' => true],
                'checkout' => ['applepay' => false, 'paypal' => false],
                'other' => ['applepay' => true, 'paypal' => false],
            ],
            $this->legacyValues()
        );
    }

    /**
     * Scenario: Express unable to run leaves the legacy buttons exactly as configured
     *   Given a live, HTTPS shop with both legacy buttons configured on, where Express owns the checkout
     *   When the shop switches to test mode, or the site is not HTTPS
     *   Then Express owns nothing
     *   And both legacy methods return what is configured, on every page
     *
     * @test
     * @dataProvider reasonsExpressCannotRun
     */
    public function it_keeps_legacy_buttons_as_configured_when_express_cannot_run(string $reason): void
    {
        $this->configureLegacyButtons();
        $this->useHttps(true);
        $this->bootExpress();
        $this->assertTrue(
            mollieWooCommerceExpressOwnsSurface('checkout'),
            'Precondition: Express owns the checkout, so the comparison below is not vacuous.'
        );

        if ($reason === 'mode_not_allowed') {
            $this->useTestMode();
        } else {
            $this->useHttps(false);
        }

        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('checkout'), "Express must not run ({$reason}).");
        $this->assertSame(self::AS_CONFIGURED, $this->legacyValues());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function reasonsExpressCannotRun(): array
    {
        return [
            'test mode is not allowed' => ['mode_not_allowed'],
            'the site is not HTTPS' => ['not_https'],
        ];
    }

    /**
     * Scenario: a settings read that fails means Express is off, never on
     *   Given a live, HTTPS shop where Express owns the checkout
     *   When reading a wallet's payment method settings throws
     *   Then the plugin answers that Express owns nothing, and does not throw
     *   And a legacy button whose own settings still read shows as configured
     *
     * @test
     */
    public function it_treats_a_failing_settings_read_as_express_disabled(): void
    {
        $this->configureLegacyButtons();
        $this->useHttps(true);
        $this->bootExpress();
        $this->assertTrue(mollieWooCommerceExpressOwnsSurface('checkout'), 'Precondition: Express can run here.');

        $option = 'mollie_wc_gateway_applepay_settings';
        $fail = static function (): void {
            throw new \RuntimeException('The options table is unavailable.');
        };
        add_filter('pre_option_' . $option, $fail);
        try {
            $owns = mollieWooCommerceExpressOwnsSurface('checkout');
            $this->onPage('checkout');
            $payPal = (new Paypal())->isExpressCheckoutEnabled();
        } finally {
            remove_filter('pre_option_' . $option, $fail);
        }

        $this->assertFalse($owns);
        $this->assertTrue($payPal, 'Express is off, so PayPal keeps its configured checkout button.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The wallets behind the decision (the adapter's translation of real shop state)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: one wallet is enough for Express to own the checkout, whichever it is
     *   Given a live, HTTPS shop with both express buttons on for the checkout
     *   And one of Apple Pay and PayPal switched off in its payment method settings
     *   When the plugin is asked whether Express owns the checkout
     *   Then it does, through the wallet that is left
     *
     * This pins the real gateway id of each wallet row: with a wrong id in config/express.php
     * that wallet would never be visible, and only a test where it stands alone can tell.
     *
     * @test
     * @dataProvider walletsStandingAlone
     */
    public function it_owns_the_checkout_through_either_wallet_alone(string $switchedOff): void
    {
        $this->configureLegacyButtons();
        $this->setGatewaySettingsForTest($switchedOff, ['enabled' => 'no']);
        $this->useHttps(true);
        $this->bootExpress();

        $this->assertTrue(mollieWooCommerceExpressOwnsSurface('checkout'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function walletsStandingAlone(): array
    {
        return [
            'PayPal alone, Apple Pay switched off' => ['applepay'],
            'Apple Pay alone, PayPal switched off' => ['paypal'],
        ];
    }

    /**
     * Scenario: switching every wallet's payment method off leaves the legacy buttons as configured
     *   Given a live, HTTPS shop where Express owns the checkout
     *   When the merchant switches both the Apple Pay and the PayPal payment methods off
     *   Then Express owns nothing
     *   And the legacy methods return their configured values again, on every page
     *
     * @test
     */
    public function it_stops_owning_the_checkout_when_the_merchant_disables_every_wallet(): void
    {
        $this->configureLegacyButtons();
        $this->useHttps(true);
        $this->bootExpress();
        $this->assertTrue(mollieWooCommerceExpressOwnsSurface('checkout'), 'Precondition: Express can run here.');

        $this->setGatewaySettingsForTest('applepay', ['enabled' => 'no']);
        $this->setGatewaySettingsForTest('paypal', ['enabled' => 'no']);

        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('checkout'));
        $this->assertSame(self::AS_CONFIGURED, $this->legacyValues());
    }

    /**
     * Scenario: a wallet counts only while its Mollie method is active on the profile
     *   Given a live, HTTPS shop with both express buttons on and both wallet payment methods enabled
     *   And the plugin's list of methods active at Mollie set to a given value
     *   When the plugin is asked whether Express owns the checkout
     *   Then Express owns it when Apple Pay or PayPal is active, and not when neither is
     *
     * @test
     * @dataProvider activeMollieMethods
     * @param array<int, string> $active
     */
    public function it_needs_a_wallet_to_be_active_at_mollie(array $active, bool $expected): void
    {
        $this->configureLegacyButtons();
        $this->useHttps(true);
        // Overridden rather than faked: the plugin registers its methods from the same fake list, so
        // changing that list would also remove the wallets from the registered set and hide this check.
        $this->bootExpress([
            'gateway.paymentMethodsEnabledAtMollie' => static function () use ($active): array {
                return $active;
            },
        ]);

        $owns = mollieWooCommerceExpressOwnsSurface('checkout');

        $this->assertSame($expected, $owns);
    }

    /**
     * @return array<string, array{0: array<int, string>, 1: bool}>
     */
    public function activeMollieMethods(): array
    {
        return [
            'both wallets active' => [['ideal', 'applepay', 'paypal'], true],
            'only PayPal active' => [['ideal', 'paypal'], true],
            'only Apple Pay active' => [['ideal', 'applepay'], true],
            'neither wallet active' => [['ideal'], false],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Apple Pay and PayPal enabled, with every legacy express setting on. The harness's fake
     * methods list already reports both as active at Mollie.
     */
    private function configureLegacyButtons(): void
    {
        $this->setGatewaySettingsForTest('applepay', [
            'enabled' => 'yes',
            self::APPLE_PAY_EXPRESS => 'yes',
        ]);
        $this->setGatewaySettingsForTest('paypal', [
            'enabled' => 'yes',
            self::PAYPAL_CART => 'yes',
            self::PAYPAL_CHECKOUT => 'yes',
        ]);
    }

    /**
     * Sets a wallet's payment method settings; a null value removes that setting.
     *
     * @param array<string, string|null> $settings
     */
    private function setWalletSettings(string $methodId, array $settings): void
    {
        $optionName = 'mollie_wc_gateway_' . $methodId . '_settings';
        $existing = get_option($optionName, []);
        $merged = array_merge(is_array($existing) ? $existing : [], $settings);
        $merged = array_filter($merged, static function ($value): bool {
            return $value !== null;
        });

        $this->setOptionForTest($optionName, $merged);
    }

    /**
     * What each legacy method answers on each page. Fresh instances, because the methods read their
     * stored settings on every call and nothing else about them matters here.
     *
     * @return array<string, array{applepay: bool, paypal: bool}>
     */
    private function legacyValues(): array
    {
        $values = [];
        foreach (self::PAGES as $page) {
            $this->onPage($page);
            $values[$page] = [
                'applepay' => (new Applepay())->isExpressCheckoutEnabled(),
                'paypal' => (new Paypal())->isExpressCheckoutEnabled(),
            ];
        }
        $this->onPage('other');

        return $values;
    }

    /**
     * is_cart() and is_checkout() through WooCommerce's own filters.
     */
    private function onPage(string $page): void
    {
        $this->removeFilters($this->pageFilters);

        $isCart = static function () use ($page): bool {
            return $page === 'cart';
        };
        $isCheckout = static function () use ($page): bool {
            return $page === 'checkout';
        };
        add_filter('woocommerce_is_cart', $isCart, PHP_INT_MAX);
        add_filter('woocommerce_is_checkout', $isCheckout, PHP_INT_MAX);
        $this->pageFilters = [['woocommerce_is_cart', $isCart], ['woocommerce_is_checkout', $isCheckout]];
    }

    /**
     * The site URL is what wc_site_is_https() reads; the request itself is always CLI.
     */
    private function useHttps(bool $https): void
    {
        $this->removeFilters($this->httpsFilters);

        $url = static function () use ($https): string {
            return ($https ? 'https' : 'http') . '://shop.example';
        };
        add_filter('pre_option_home', $url, PHP_INT_MAX);
        add_filter('pre_option_siteurl', $url, PHP_INT_MAX);
        $this->httpsFilters = [['pre_option_home', $url], ['pre_option_siteurl', $url]];
    }

    /**
     * @param array<int, array{0: string, 1: callable}> $filters
     */
    private function removeFilters(array &$filters): void
    {
        foreach ($filters as [$hook, $callback]) {
            remove_filter($hook, $callback, PHP_INT_MAX);
        }
        $filters = [];
    }
}
