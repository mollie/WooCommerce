<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WP_Query;
use WP_Scripts;

/**
 * What the Express Component puts on a page, and where (REQ-G1, NF-1, NF-4; AC-2, AC-19, AC-34).
 *
 * Mollie.js v2 publishes itself as window.Mollie unless window.Mollie is already a function or a
 * mollie.js script tag carries a 'compatible' query parameter; v1, a runtime dependency of the block
 * checkout, then overwrites it without an error anywhere. So the one v2 handle is registered for
 * exactly https://js.mollie.com/v2/mollie.js?compatible, with no version argument that could change
 * the URL, and v1 keeps its own registration untouched.
 *
 * Both load only on a block checkout that Express owns: not on the block cart, the product page or
 * the classic checkout, and not where Express cannot run (test mode, plain HTTP, no wallet with its
 * checkout button on). mollieExpressData is an allowlist: the REST base, the nonce the two routes
 * admit, the locale for Mollie.js, the wallets to offer and the shopper-facing messages. Nothing
 * identifying the Mollie account and nothing about the shopper crosses to the browser.
 *
 * Real WordPress, real plugin boot, real page queries. Only this boot's wp_enqueue_scripts
 * callbacks run, so earlier boots in the same process cannot enqueue on its behalf. The shop is
 * the one ExpressCheckoutFixtures sets up: live, HTTPS, PayPal the only express wallet.
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
     * The values mollie.js v2 treats as "do not show this wallet" (see the stand-in,
     * tests/qa/express/fake-mollie/mollie-v2-stub.js). A wallet missing from the map is shown.
     */
    private const HIDDEN = ['hidden', 'never', 'none', false];

    private const APPLE_PAY_EXPRESS = 'mollie_apple_pay_button_enabled_express_checkout';

    private const PAYPAL_CHECKOUT = 'mollie_paypal_button_enabled_checkout';

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
        // Registrations are objects shared by every clone of WP_Scripts: copy them one by one so
        // what a scenario registers, enqueues or localizes is gone for the next one.
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
            // The legacy buttons step aside only where is_checkout() holds: loading here would put two
            // express offers on one page.
            'a page with the checkout block that is not the checkout page' => ['stray checkout block', 'owned'],
            'the order-received page' => ['order received', 'owned'],
            'block checkout in test mode' => ['block checkout', 'test mode'],
            'block checkout, no wallet button on' => ['block checkout', 'no wallet button'],
            'block checkout on plain HTTP' => ['block checkout', 'plain HTTP'],
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
     *   And none of the marked secrets or shopper details, and no profile id, is in it
     *   And the nonce is one the express routes admit
     *   And restUrl is the plugin's REST base
     *   And the messages include the one telling the shopper to fill in the shipping details
     *
     * @test
     */
    public function it_localizes_only_the_allowlisted_express_data(): void
    {
        $this->bootExpressOwning(['wp_enqueue_scripts']);
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));

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
        $this->assertContains(
            __('Your order contains items to ship. Please fill in the shipping details to use express checkout.', 'mollie-payments-for-woocommerce'),
            $data['messages'],
            'The blocked state needs the same shipping message the store answers with.'
        );
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
            $this->bootExpressOwning(['wp_enqueue_scripts']);
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
        // WooCommerce answers is_cart()/is_checkout() only once 'wp' has run, and caches the answer
        // for the rest of the request. This is a new request for a new page: mark 'wp' as done
        // without running its callbacks, and forget the previous page's answer.
        $GLOBALS['wp_actions']['wp'] = max(1, (int) ($GLOBALS['wp_actions']['wp'] ?? 0));
        $this->forgetPageType();

        do_action('wp_enqueue_scripts');
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
