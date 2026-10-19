<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\PaymentMethods\Applepay;
use Mollie\WooCommerce\PaymentMethods\Paypal;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WP_Query;
use WP_Scripts;

/**
 * Which pages load the Mollie.js v2 bundle and what mollieExpressData exposes to the browser.
 * The v2 URL carries ?compatible so Mollie.js v1 on the block checkout does not overwrite it.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressAssets
 */
class ExpressAssetsTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const V2_URL = 'https://js.mollie.com/v2/mollie.js?compatible';

    private const V1_URL = 'https://js.mollie.com/v1/mollie.js';

    private const ALLOWED_KEYS = ['buttons', 'locale', 'messages', 'nonce', 'restUrl'];

    private const WALLETS = ['applepay', 'googlepay', 'paypal'];

    /**
     * Values mollie.js v2 treats as "do not show this wallet"; a wallet missing from the map is shown.
     */
    private const HIDDEN = ['hidden', 'never', 'none', false];

    private const APPLE_PAY_EXPRESS = 'mollie_apple_pay_button_enabled_express_checkout';

    private const PAYPAL_CHECKOUT = 'mollie_paypal_button_enabled_checkout';

    private const OWNS_SURFACE = 'mollie_wc_express_owns_surface';

    /**
     * @var array<int, int>
     */
    private array $pageIds = [];

    private ?WP_Scripts $scriptsBackup = null;

    private ?int $wpActionBackup = null;

    /**
     * @var array<string, mixed>
     */
    private array $globalsBackup = [];

    /**
     * Products a scenario made virtual, with what they were, restored in tearDown().
     *
     * @var array<int, bool>
     */
    private array $virtualBackup = [];

    /**
     * Customers a scenario created, deleted in tearDown().
     *
     * @var array<int, int>
     */
    private array $userIds = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpExpressCheckout();

        $this->globalsBackup = [
            'wp_query' => $GLOBALS['wp_query'] ?? null,
            'wp_the_query' => $GLOBALS['wp_the_query'] ?? null,
            'post' => $GLOBALS['post'] ?? null,
        ];
        $this->wpActionBackup = $GLOBALS['wp_actions']['wp'] ?? null;
        // Registrations are shared objects across WP_Scripts clones: copy each so scenarios stay isolated.
        $scripts = wp_scripts();
        $this->scriptsBackup = clone $scripts;
        $this->scriptsBackup->registered = array_map(static function ($dependency) {
            return clone $dependency;
        }, $scripts->registered);
    }

    public function tearDown(): void
    {
        foreach ($this->globalsBackup as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        // Only the 'wp' counter this test sets: every other action counted meanwhile really ran.
        if ($this->wpActionBackup === null) {
            unset($GLOBALS['wp_actions']['wp']);
        } else {
            $GLOBALS['wp_actions']['wp'] = $this->wpActionBackup;
        }
        $this->forgetPageType();
        unset($GLOBALS['wp']->query_vars['order-received']);
        if ($this->scriptsBackup !== null) {
            $GLOBALS['wp_scripts'] = $this->scriptsBackup;
        }
        foreach ($this->pageIds as $pageId) {
            wp_delete_post($pageId, true);
        }
        $this->pageIds = [];
        foreach ($this->virtualBackup as $productId => $virtual) {
            $product = wc_get_product($productId);
            $product->set_virtual($virtual);
            $product->save();
        }
        $this->virtualBackup = [];
        if ($this->userIds !== []) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($this->userIds as $userId) {
                wp_delete_user($userId);
            }
            $this->userIds = [];
        }

        $this->tearDownExpressCheckout();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Where the v2 bundle loads
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: an owned block checkout loads the v2 bundle under the one URL that keeps it Mollie2
     *   Given a live, HTTPS shop where PayPal has its express button on the checkout
     *   And the checkout page holds the checkout block
     *   When the checkout page enqueues its scripts
     *   Then one handle is registered for exactly https://js.mollie.com/v2/mollie.js?compatible
     *   And it has no version argument, so WordPress appends nothing to that URL
     *   And it is part of what the page loads
     *   And Mollie.js v1 keeps its own registration
     *
     * @test
     */
    public function it_registers_the_v2_bundle_with_the_compatible_url_on_an_owned_block_checkout(): void
    {
        $this->openPage('block checkout');

        $v2 = $this->handlesFor(self::V2_URL);
        $this->assertCount(1, $v2, 'Exactly one handle must load the v2 bundle, under exactly this URL.');
        $this->assertSame([], $this->handlesMatching('#^https://js\.mollie\.com/v2/#', self::V2_URL), 'No other v2 URL may be registered.');
        $handle = $v2[0];
        $this->assertNull(wp_scripts()->registered[$handle]->ver, 'A version argument would append ?ver=… to the URL.');
        $this->assertTrue($this->isLoaded($handle), 'The v2 handle must be enqueued, or be a dependency of what is.');

        $v1 = wp_scripts()->registered['mollie'] ?? null;
        $this->assertNotNull($v1, 'Mollie.js v1 must stay registered.');
        $this->assertSame(self::V1_URL, $v1->src);
        $blockIndex = wp_scripts()->registered['mollie_block_index'] ?? null;
        if ($blockIndex !== null) {
            $this->assertContains('mollie', $blockIndex->deps, 'mollie_block_index keeps v1 as a runtime dependency.');
        }
    }

    /**
     * Scenario: nothing of the Express Component loads where Express does not own a block checkout
     *   Given one of: the block cart, a product page, the classic checkout, a page holding the checkout
     *         block that is not the store's checkout page, the order-received page, test mode, no
     *         wallet with its checkout button on, or a site on plain HTTP
     *   When that page enqueues its scripts
     *   Then the v2 bundle is not part of what the page loads
     *   And no script carries mollieExpressData
     *
     * @test
     * @dataProvider pagesExpressDoesNotOwn
     */
    public function it_loads_nothing_where_express_does_not_own_the_block_checkout(string $page, string $shop): void
    {
        switch ($shop) {
            case 'test mode':
                $this->useTestMode();
                break;
            case 'no wallet button':
                $this->setGatewaySettingsForTest('paypal', [self::PAYPAL_CHECKOUT => 'no']);
                $this->setGatewaySettingsForTest('applepay', [self::APPLE_PAY_EXPRESS => 'no']);
                break;
            case 'plain HTTP':
                // Added after the fixture's HTTPS filter at the same priority, so it runs last and wins.
                $http = static function (): string {
                    return 'http://shop.example';
                };
                $this->addTestFilter('pre_option_home', $http, PHP_INT_MAX);
                $this->addTestFilter('pre_option_siteurl', $http, PHP_INT_MAX);
                break;
        }

        $this->openPage($page);

        foreach ($this->handlesMatching('#^https://js\.mollie\.com/v2/#') as $handle) {
            $this->assertFalse($this->isLoaded($handle), "The v2 bundle ('{$handle}') must not load here.");
        }
        $this->assertNull($this->localizedExpressData(), 'mollieExpressData must not be printed here.');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function pagesExpressDoesNotOwn(): array
    {
        return [
            'block cart' => ['block cart', 'owned'],
            'product page' => ['product', 'owned'],
            'classic checkout' => ['classic checkout', 'owned'],
            // Legacy buttons step aside only where is_checkout() holds: two express offers otherwise.
            'a page with the checkout block that is not the checkout page' => ['stray checkout block', 'owned'],
            'the order-received page' => ['order received', 'owned'],
            'block checkout in test mode' => ['block checkout', 'test mode'],
            'block checkout, no wallet button on' => ['block checkout', 'no wallet button'],
            'block checkout on plain HTTP' => ['block checkout', 'plain HTTP'],
        ];
    }

    /**
     * Scenario: the cart is totalled for ownership only on the block checkout
     *   Given a guest with a cart priced at page load
     *   When a page enqueues its scripts
     *   Then WooCommerce totals the cart for that only on the store's block checkout page
     *
     * @test
     * @dataProvider pagesThatTotalTheCart
     */
    public function it_totals_the_cart_for_ownership_only_on_the_block_checkout(string $page, bool $totals): void
    {
        $this->bootExpressOwning(['wp_enqueue_scripts', self::OWNS_SURFACE]);
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
        $calculated = 0;
        $this->addTestFilter('woocommerce_before_calculate_totals', static function () use (&$calculated): void {
            $calculated++;
        });

        $this->openPage($page, false);

        $this->assertSame($totals, $calculated > 0, $totals ? 'Ownership is decided from the totalled cart.' : 'Only the block checkout may total the cart.');
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function pagesThatTotalTheCart(): array
    {
        return [
            'block checkout' => ['block checkout', true],
            'block cart' => ['block cart', false],
            'product page' => ['product', false],
            'classic checkout' => ['classic checkout', false],
            'a page with the checkout block that is not the checkout page' => ['stray checkout block', false],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // What is localized
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: the browser gets the allowlist and nothing else
     *   Given the marked API keys and webhook secret of the harness
     *   And a guest with a cart and marked shopper details in the checkout form
     *   When the owned block checkout enqueues its scripts
     *   Then mollieExpressData holds exactly restUrl, nonce, locale, buttons and messages
     *   And no marked value and no profile id is in it
     *   And the nonce is one the express routes admit
     *   And restUrl is the plugin's REST base
     *   And the messages hold the two labels of the "Ships to" line, and nothing for a waiting state,
     *       which is never shown
     *
     * @test
     */
    public function it_localizes_only_the_allowlisted_express_data(): void
    {
        $this->bootExpressOwning(['wp_enqueue_scripts']);
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');

        $this->openPage('block checkout', false);
        $data = $this->localizedExpressData();

        $this->assertIsArray($data, 'mollieExpressData must be printed on an owned block checkout.');
        $keys = array_keys($data);
        sort($keys);
        $this->assertSame(self::ALLOWED_KEYS, $keys, 'mollieExpressData is an allowlist.');
        $this->assertNothingLeakedToBrowser($data);
        $serialised = (string) wp_json_encode($data);
        $this->assertStringNotContainsString(CanaryData::LIVE_API_KEY, $serialised);
        $this->assertStringNotContainsString(CanaryData::WEBHOOK_SECRET, $serialised);

        $this->assertIsString($data['nonce']);
        $this->assertNotFalse(wp_verify_nonce($data['nonce'], ExpressRoutes::NONCE_ACTION), 'The nonce must be the one the routes admit.');
        $this->assertSame(rest_url('mollie/v1/'), $data['restUrl']);

        $this->assertIsArray($data['messages']);
        $this->assertSame(__('Ships to %1$s · %2$s', 'mollie-payments-for-woocommerce'), $data['messages']['shipsTo'] ?? null);
        $this->assertSame(__('Change', 'mollie-payments-for-woocommerce'), $data['messages']['change'] ?? null);
        foreach (['shippingIncomplete', 'waitingForShipping'] as $gone) {
            $this->assertArrayNotHasKey($gone, $data['messages'], "The blocked state renders nothing, so '{$gone}' is not sent.");
        }
        foreach ($data['messages'] as $key => $message) {
            $this->assertIsString($message, "Message '{$key}' must be a string.");
            $this->assertNotSame('', $message, "Message '{$key}' must not be empty.");
        }
    }

    /**
     * Scenario: Mollie.js gets the validated shop locale
     *   Given the shop's WordPress locale
     *   When the owned block checkout enqueues its scripts
     *   Then mollieExpressData.locale is that locale mapped onto one Mollie accepts
     *
     * @test
     * @dataProvider locales
     */
    public function it_localizes_the_validated_locale(string $wordPressLocale, string $expected): void
    {
        $this->addTestFilter('locale', static function () use ($wordPressLocale): string {
            return $wordPressLocale;
        }, PHP_INT_MAX);

        $this->openPage('block checkout');
        $data = $this->localizedExpressData();

        $this->assertIsArray($data);
        $this->assertSame($expected, $data['locale']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function locales(): array
    {
        return [
            'accepted' => ['nl_NL', 'nl_NL'],
            'formal variant' => ['de_DE_formal', 'de_DE'],
            'not accepted' => ['xx_XX', AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE],
        ];
    }

    /**
     * Scenario: only the wallets the merchant turned on are offered
     *   Given PayPal enabled with its express button on the checkout
     *   And the Apple Pay payment method switched off, though its express button setting is on
     *   And no Google Pay payment method in the plugin
     *   When the owned block checkout enqueues its scripts
     *   Then the buttons map offers PayPal
     *   And tells mollie.js to hide Apple Pay and Google Pay, which it would otherwise show
     *   And names no wallet outside the wallets table
     *
     * @test
     */
    public function it_offers_only_the_wallets_the_merchant_turned_on(): void
    {
        $this->setGatewaySettingsForTest('applepay', ['enabled' => 'no', self::APPLE_PAY_EXPRESS => 'yes']);

        $this->openPage('block checkout');
        $data = $this->localizedExpressData();

        $this->assertIsArray($data);
        $this->assertIsArray($data['buttons']);
        foreach (array_keys($data['buttons']) as $wallet) {
            $this->assertContains($wallet, self::WALLETS, "'{$wallet}' is not a row of the wallets table.");
        }
        $this->assertSame(
            ['applepay' => false, 'googlepay' => false, 'paypal' => true],
            $this->offered($data['buttons']),
            'Offered wallets are wrong in ' . wp_json_encode($data['buttons'])
        );
    }

    /**
     * Scenario: Google Pay is offered once its payment method is turned on
     *   Given Google Pay activated at Mollie
     *   And PayPal enabled with its express button on the checkout, Apple Pay's express button off
     *   And the Google Pay payment method's one setting on or off
     *   When the owned block checkout enqueues its scripts
     *   Then the buttons map offers Google Pay exactly when that setting is on
     *   And offers PayPal and hides Apple Pay either way
     *
     * @test
     * @dataProvider googlePaySetting
     */
    public function it_offers_google_pay_once_its_payment_method_is_turned_on(string $enabled, bool $offered): void
    {
        $this->fakeMollie()->setMethods(['ideal', 'creditcard', 'banktransfer', 'paypal', 'applepay', 'googlepay']);
        $this->flushMollieMethodsCache();
        $this->setGatewaySettingsForTest('googlepay', ['enabled' => $enabled]);

        $this->openPage('block checkout');
        $data = $this->localizedExpressData();

        $this->assertIsArray($data);
        $this->assertIsArray($data['buttons']);
        $this->assertSame(
            ['applepay' => false, 'googlepay' => $offered, 'paypal' => true],
            $this->offered($data['buttons']),
            'Offered wallets are wrong in ' . wp_json_encode($data['buttons'])
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function googlePaySetting(): array
    {
        return [
            'switched on' => ['yes', true],
            'switched off' => ['no', false],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Owned only when the price is known at page load
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: Express owns the block checkout only when the price is known when the page loads
     *   Given Apple Pay and PayPal with their legacy checkout buttons configured on
     *   And the cart, the shopper and the shop's tax basis as they are when the checkout page loads
     *   When the block checkout page enqueues its scripts
     *   Then mollieExpressData is printed exactly when the cart is ready: nothing to ship and no tax
     *        that waits for an address nobody gave, or a complete shipping address with a chosen rate
     *   And the legacy Apple Pay and PayPal buttons step aside exactly then, and show as configured otherwise
     *
     * @test
     * @dataProvider pageLoads
     */
    public function it_owns_the_block_checkout_at_page_load_only_when_the_price_is_known(
        bool $ships,
        string $taxes,
        string $shopper,
        bool $owned
    ): void {

        $this->legacyButtonsConfiguredOn();
        if ($taxes === 'off') {
            $this->setOptionForTest('woocommerce_calc_taxes', 'no');
        } else {
            $this->setOptionForTest('woocommerce_calc_taxes', 'yes');
            $this->setOptionForTest('woocommerce_tax_based_on', $taxes);
        }
        if (!$ships) {
            $this->makeVirtual('simple');
        }
        $this->bootExpressOwning(['wp_enqueue_scripts', self::OWNS_SURFACE]);
        $this->shopperAtPageLoad($shopper);
        $this->assertSame($ships, WC()->cart->needs_shipping(), 'The cart must ship as the scenario says.');

        $this->openPage('block checkout', false);

        $this->assertSame(
            $owned,
            $this->localizedExpressData() !== null,
            $owned ? 'mollieExpressData must be printed on an owned checkout.' : 'mollieExpressData must not be printed here.'
        );
        $this->assertSame(
            ['applepay' => !$owned, 'paypal' => !$owned],
            $this->legacyButtons(),
            $owned ? 'The legacy buttons step aside where Express owns the checkout.' : 'The legacy buttons show as configured.'
        );
    }

    /**
     * @return array<string, array{0: bool, 1: string, 2: string, 3: bool}>
     */
    public function pageLoads(): array
    {
        return [
            'nothing ships, taxes off, guest' => [false, 'off', 'guest, nothing typed', true],
            'nothing ships, taxes on the shop address, guest' => [false, 'base', 'guest, nothing typed', true],
            'nothing ships, taxes on the billing address, guest' => [false, 'billing', 'guest, nothing typed', false],
            'nothing ships, taxes on the shipping address, guest' => [false, 'shipping', 'guest, nothing typed', false],
            'nothing ships, taxes on the billing address, its country saved on the account' => [false, 'billing', 'customer, billing country saved', true],
            'nothing ships, taxes on the shipping address, its country saved on the account' => [false, 'shipping', 'customer, shipping country saved', true],
            'nothing ships, taxes on the billing address, only the shipping country saved' => [false, 'billing', 'customer, shipping country saved', false],
            'ships, guest who typed only a country' => [true, 'shipping', 'guest, only a country typed', false],
            'ships, guest who typed nothing' => [true, 'shipping', 'guest, nothing typed', false],
            'ships, customer with a saved address and a chosen rate' => [true, 'shipping', 'customer, shipping address saved', true],
            'ships, returning guest whose session holds the address and a rate' => [true, 'shipping', 'returning guest, address and rate in the session', true],
        ];
    }

    /**
     * Scenario: ownership is decided once per request, whoever asks first
     *   Given a block checkout whose cart is ready, or not, when ownership is first asked
     *   When the page and the legacy buttons ask, in either order, and the cart changes in between
     *   Then both get the first answer: a page that was not owned stays without the Express Component
     *        after the shopper completes the form, and one that was owned keeps it
     *
     * @test
     * @dataProvider firstAskers
     */
    public function it_decides_ownership_once_per_request(string $asksFirst, bool $readyAtFirst): void
    {
        $this->bootExpressOwning(['wp_enqueue_scripts', self::OWNS_SURFACE]);
        $this->actAsGuest();
        $this->cartWith(['simple']);
        if ($readyAtFirst) {
            $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
            $this->chooseRate('standard');
        } else {
            $this->fillCheckoutForm([], ['country' => 'LU']);
            $this->recalculate();
        }

        if ($asksFirst === 'the legacy buttons') {
            $first = $this->ownsTheCheckout();
            $this->changeTheCart($readyAtFirst);
            $this->openPage('block checkout', false);
            $later = [$this->localizedExpressData() !== null, $this->ownsTheCheckout()];
        } else {
            $this->openPage('block checkout', false);
            $first = $this->localizedExpressData() !== null;
            $this->changeTheCart($readyAtFirst);
            $later = [$this->ownsTheCheckout(), $this->ownsTheCheckout()];
        }

        $this->assertSame($readyAtFirst, $first, 'Precondition: the first answer follows the cart at that moment.');
        $this->assertSame([$readyAtFirst, $readyAtFirst], $later, 'Every later ask in the request must get the first answer.');
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function firstAskers(): array
    {
        return [
            'the page asks first, ready' => ['the page', true],
            'the page asks first, not ready' => ['the page', false],
            'the legacy buttons ask first, ready' => ['the legacy buttons', true],
            'the legacy buttons ask first, not ready' => ['the legacy buttons', false],
        ];
    }

    /**
     * Scenario: no cart loaded means not owned, without an error
     *   Given a live, HTTPS shop where Express could run
     *   And WooCommerce's cart not loaded when ownership is first asked
     *   When the legacy buttons ask and the block checkout enqueues its scripts
     *   Then the checkout is not owned and mollieExpressData is not printed
     *
     * @test
     */
    public function it_does_not_own_the_checkout_when_the_cart_is_not_loaded(): void
    {
        $this->bootExpressOwning(['wp_enqueue_scripts', self::OWNS_SURFACE]);
        $cart = WC()->cart;
        WC()->cart = null;
        try {
            $owns = $this->ownsTheCheckout();
            $this->openPage('block checkout', false);
        } finally {
            WC()->cart = $cart;
        }

        $this->assertFalse($owns);
        $this->assertNull($this->localizedExpressData());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Makes the given page the main query and lets it enqueue its scripts, with only this boot's
     * wp_enqueue_scripts callbacks attached.
     */
    private function openPage(string $page, bool $boot = true): void
    {
        if ($boot) {
            $this->bootExpressOwning(['wp_enqueue_scripts', self::OWNS_SURFACE]);
            // Owned only with a cart priced at page load: a guest whose form is complete, with a rate.
            $this->actAsGuest();
            $this->cartWith(['simple']);
            $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
            $this->chooseRate('standard');
        }

        switch ($page) {
            case 'block checkout':
                $pageId = $this->page('Express checkout', '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->');
                $this->setOptionForTest('woocommerce_checkout_page_id', $pageId);
                $args = ['page_id' => $pageId];
                break;
            case 'stray checkout block':
                // The store's checkout page stays whatever the site has; this is another page.
                $pageId = $this->page('Not the checkout', '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->');
                $args = ['page_id' => $pageId];
                break;
            case 'order received':
                $pageId = $this->page('Express checkout', '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->');
                $this->setOptionForTest('woocommerce_checkout_page_id', $pageId);
                $args = ['page_id' => $pageId];
                $GLOBALS['wp']->query_vars['order-received'] = '1';
                break;
            case 'classic checkout':
                $pageId = $this->page('Classic checkout', '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->');
                $this->setOptionForTest('woocommerce_checkout_page_id', $pageId);
                $args = ['page_id' => $pageId];
                break;
            case 'block cart':
                $pageId = $this->page('Express cart', '<!-- wp:woocommerce/cart --><div class="wp-block-woocommerce-cart"></div><!-- /wp:woocommerce/cart -->');
                $this->setOptionForTest('woocommerce_cart_page_id', $pageId);
                $args = ['page_id' => $pageId];
                break;
            case 'product':
                $productId = (int) wc_get_product_id_by_sku(ProductPresets::get()['simple']['sku']);
                $this->assertGreaterThan(0, $productId, "The 'simple' preset product does not exist.");
                $args = ['p' => $productId, 'post_type' => 'product'];
                break;
            default:
                $this->fail("Unknown page '{$page}'.");
        }

        $query = new WP_Query($args);
        $GLOBALS['wp_query'] = $query;
        $GLOBALS['wp_the_query'] = $query;
        $GLOBALS['post'] = $query->post;
        // is_cart()/is_checkout() answer only after 'wp' and cache it: mark 'wp' done and forget the cache.
        $GLOBALS['wp_actions']['wp'] = max(1, (int) ($GLOBALS['wp_actions']['wp'] ?? 0));
        $this->forgetPageType();

        do_action('wp_enqueue_scripts');
    }

    /**
     * The shopper, the cart and the form as they are when the checkout page loads. A customer's
     * account data is saved before WooCommerce reads it; their form is left as WooCommerce fills it.
     */
    private function shopperAtPageLoad(string $shopper): void
    {
        $saved = [
            'customer, billing country saved' => ['billing_country' => 'LU'],
            'customer, shipping country saved' => ['shipping_country' => 'LU'],
            'customer, shipping address saved' => [
                'shipping_first_name' => 'Ada',
                'shipping_last_name' => 'Lovelace',
                'shipping_address_1' => 'Rue du Fort 12',
                'shipping_postcode' => '1234',
                'shipping_city' => 'Town',
                'shipping_country' => 'LU',
            ],
        ];
        if (isset($saved[$shopper])) {
            $this->customerWithSaved($saved[$shopper]);
            $this->cartWith(['simple']);
            if ($shopper === 'customer, shipping address saved') {
                $this->chooseRate('standard');
            } else {
                $this->recalculate();
            }

            return;
        }

        $this->actAsGuest();
        $this->cartWith(['simple']);
        switch ($shopper) {
            case 'guest, nothing typed':
                $this->fillCheckoutForm([], []);
                $this->recalculate();
                break;
            case 'guest, only a country typed':
                $this->fillCheckoutForm([], ['country' => 'LU']);
                $this->recalculate();
                break;
            case 'returning guest, address and rate in the session':
                $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
                $this->chooseRate('standard');
                break;
            default:
                $this->fail("Unknown shopper '{$shopper}'.");
        }
    }

    /**
     * A logged-in customer whose account holds these fields, read by WooCommerce as on a new visit.
     *
     * @param array<string, string> $meta
     */
    private function customerWithSaved(array $meta): void
    {
        $userId = wp_insert_user([
            'user_login' => 'express-' . wp_generate_password(8, false),
            'user_pass' => wp_generate_password(),
            'user_email' => 'express-' . wp_generate_password(8, false) . '@example.com',
            'role' => 'customer',
        ]);
        $this->assertIsInt($userId);
        $this->userIds[] = $userId;
        foreach ($meta as $key => $value) {
            update_user_meta($userId, $key, $value);
        }
        $this->actAsCustomer($userId);
        WC()->customer = new \WC_Customer($userId, true);
    }

    private function makeVirtual(string $preset): void
    {
        $product = wc_get_product((int) wc_get_product_id_by_sku(ProductPresets::get()[$preset]['sku']));
        $this->assertInstanceOf(\WC_Product::class, $product);
        if (!array_key_exists($product->get_id(), $this->virtualBackup)) {
            $this->virtualBackup[$product->get_id()] = $product->get_virtual();
        }
        $product->set_virtual(true);
        $product->save();
    }

    /**
     * Ready becomes not ready by emptying the cart; not ready becomes ready by completing the form.
     */
    private function changeTheCart(bool $wasReady): void
    {
        if ($wasReady) {
            WC()->cart->empty_cart();

            return;
        }
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
    }

    private function ownsTheCheckout(): bool
    {
        return (bool) apply_filters(self::OWNS_SURFACE, false, 'checkout');
    }

    /**
     * Apple Pay and PayPal enabled with their legacy checkout buttons on.
     */
    private function legacyButtonsConfiguredOn(): void
    {
        $this->setGatewaySettingsForTest('applepay', ['enabled' => 'yes', self::APPLE_PAY_EXPRESS => 'yes']);
        $this->setGatewaySettingsForTest('paypal', ['enabled' => 'yes', self::PAYPAL_CHECKOUT => 'yes']);
    }

    /**
     * What each legacy express button answers on the page now open.
     *
     * @return array{applepay: bool, paypal: bool}
     */
    private function legacyButtons(): array
    {
        $this->assertTrue(is_checkout(), 'The legacy buttons must be asked on the checkout page.');

        return [
            'applepay' => (new Applepay())->isExpressCheckoutEnabled(),
            'paypal' => (new Paypal())->isExpressCheckoutEnabled(),
        ];
    }

    private function forgetPageType(): void
    {
        foreach (['is_cart_page', 'is_checkout_page'] as $property) {
            if (property_exists(CartCheckoutUtils::class, $property)) {
                $cache = new \ReflectionProperty(CartCheckoutUtils::class, $property);
                $cache->setAccessible(true);
                $cache->setValue(null, null);
            }
        }
    }

    private function page(string $title, string $content): int
    {
        $pageId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ]);
        $this->assertIsInt($pageId);
        $this->assertGreaterThan(0, $pageId);
        $this->pageIds[] = $pageId;

        return $pageId;
    }

    /**
     * @return array<int, string>
     */
    private function handlesFor(string $src): array
    {
        $handles = [];
        foreach (wp_scripts()->registered as $handle => $dependency) {
            if ($dependency->src === $src) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * @return array<int, string>
     */
    private function handlesMatching(string $pattern, ?string $except = null): array
    {
        $handles = [];
        foreach (wp_scripts()->registered as $handle => $dependency) {
            if (is_string($dependency->src) && preg_match($pattern, $dependency->src) === 1 && $dependency->src !== $except) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * Enqueued, or reachable through the dependencies of something enqueued — including
     * mollie_block_index, which the checkout block enqueues when it renders.
     */
    private function isLoaded(string $handle): bool
    {
        $pending = array_merge(wp_scripts()->queue, ['mollie_block_index']);
        $seen = [];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current === $handle) {
                return true;
            }
            if (isset($seen[$current]) || !isset(wp_scripts()->registered[$current])) {
                continue;
            }
            $seen[$current] = true;
            $pending = array_merge($pending, wp_scripts()->registered[$current]->deps);
        }

        return false;
    }

    /**
     * mollieExpressData as the browser would receive it, from whichever script carries it.
     *
     * @return array<string, mixed>|null
     */
    private function localizedExpressData(): ?array
    {
        foreach (wp_scripts()->registered as $dependency) {
            $inline = $dependency->extra['data'] ?? '';
            if (!is_string($inline) || preg_match('/var mollieExpressData = (\{.*?\});\s*$/ms', $inline, $match) !== 1) {
                continue;
            }
            $decoded = json_decode($match[1], true);
            $this->assertIsArray($decoded, 'mollieExpressData is not valid JSON: ' . $match[1]);

            return $decoded;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $buttons
     * @return array<string, bool>
     */
    private function offered(array $buttons): array
    {
        $offered = [];
        foreach (self::WALLETS as $wallet) {
            $entry = $buttons[$wallet] ?? null;
            $offered[$wallet] = !(is_array($entry) && in_array($entry['visibility'] ?? null, self::HIDDEN, true));
        }

        return $offered;
    }
}
