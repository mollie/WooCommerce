<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerceTests\Integration\Common\Traits\CreateTestOrders;
use Mollie\WooCommerceTests\Integration\Common\Traits\CreateTestProducts;
use Psr\Container\ContainerInterface;
use WC_Order;
use WC_Order_Item_Product;
use WC_Payment_Token_CC;
use WC_Subscription;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;


class IntegrationMockedTestCase extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration, CreateTestOrders, CreateTestProducts;

    /**
     * Every hook of the site as setUp() found it. Each bootstrapModule() adds hooks bound to that
     * test's container (and its mocked API), so they are given back after the test.
     *
     * @var array<string, \WP_Hook>
     */
    private array $baseHookBackup = [];

    /**
     * The newest post id when the test started. Orders get their id from the posts table in both
     * storages (HPOS through a placeholder post), so every order above it is the test's own. Null
     * when this setUp() did not run (a subclass that overrides it without calling it): then nothing
     * is deleted, because nothing tells the test's orders from the site's.
     */
    private ?int $newestPostIdBefore = null;

    /**
     * @throws \WC_Data_Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->baseHookBackup = [];
        foreach ($GLOBALS['wp_filter'] as $name => $hook) {
            $this->baseHookBackup[$name] = clone $hook;
        }

        $this->customer_id = $this->createCustomerIfNotExists();
        $this->createTestProducts();
        // After the shared fixtures, which are created once and reused by every test.
        $this->newestPostIdBefore = $this->newestPostId();
        //$this->createTestCoupons();
    }

    public function tearDown(): void
    {
        // This cleans up everything created during tests
        //$this->cleanupTestData();
        if ($this->baseHookBackup !== []) {
            $GLOBALS['wp_filter'] = $this->baseHookBackup;
            $this->baseHookBackup = [];
        }
        $this->deleteOrdersCreatedByTheTest();
        parent::tearDown();
    }

    /**
     * Every order, subscription and refund the test created, in any status and in either storage,
     * notes and items with it, and every product it created, so the shared site does not fill up and
     * a later scenario (cleanup above all, which selects orders by age) never finds another test's
     * order. The preset products and the test customer are shared fixtures and stay.
     */
    private function deleteOrdersCreatedByTheTest(): void
    {
        $watermark = $this->newestPostIdBefore;
        $this->newestPostIdBefore = null;
        if ($watermark === null || $watermark <= 0) {
            return;
        }
        global $wpdb;
        $types = ['shop_order', 'shop_subscription', 'shop_order_refund'];
        $in = implode(',', array_fill(0, count($types), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are WordPress's and WooCommerce's.
        $inPosts = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ({$in}) ORDER BY post_type = 'shop_order_refund' DESC, ID DESC",
            array_merge([$watermark], $types)
        )));
        $hposTable = $wpdb->prefix . 'wc_orders';
        $inHpos = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $hposTable)) === $hposTable
            ? array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$hposTable} WHERE id > %d AND type IN ({$in}) ORDER BY type = 'shop_order_refund' DESC, id DESC",
                array_merge([$watermark], $types)
            )))
            : [];
        // phpcs:enable

        $this->deleteOrders($inPosts);
        if ($inHpos !== []) {
            $this->withHposStorage(function () use ($inHpos): void {
                $this->deleteOrders($inHpos);
            });
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name is WordPress's.
        $products = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ('product_variation', 'product') ORDER BY post_type = 'product_variation' DESC, ID DESC",
            $watermark
        )));
        foreach ($products as $productId) {
            $product = wc_get_product($productId);
            if ($product instanceof \WC_Product) {
                $product->delete(true);
            }
        }
    }

    /**
     * @param array<int, int> $orderIds Refunds first, so a parent's deletion finds nothing left.
     */
    private function deleteOrders(array $orderIds): void
    {
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if ($order instanceof \WC_Abstract_Order) {
                $order->delete(true);
            }
        }
    }

    private function newestPostId(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
    }

    protected function withHposStorage(callable $run): void
    {
        $yes = static function (): string {
            return 'yes';
        };
        $hooks = ['pre_option_woocommerce_custom_orders_table_enabled', 'pre_option_woocommerce_feature_custom_order_tables_enabled'];
        foreach ($hooks as $hook) {
            add_filter($hook, $yes);
        }
        try {
            $run();
        } finally {
            foreach ($hooks as $hook) {
                remove_filter($hook, $yes);
            }
        }
    }

    /**
     * @param array<string, callable> $overriddenServices
     * @return ContainerInterface
     */
    protected function bootstrapModule(array $overriddenServices = []): ContainerInterface
    {
        $module = new class ($overriddenServices) implements ServiceModule, ExecutableModule {
            use ModuleClassNameIdTrait;

            public function __construct(array $services)
            {
                $this->services = $services;
            }

            public function services(): array
            {
                return $this->services;
            }

            public function run(ContainerInterface $c): bool
            {
                return true;
            }
        };

        $rootDir = ROOT_DIR;
        $bootstrap = require("$rootDir/bootstrap.php");
        return $bootstrap($rootDir, [$module]);
    }

    public function createCustomerIfNotExists(int $customer_id = 1): int
    {
        $customer = new \WC_Customer($customer_id);
        if (empty($customer->get_email())) {
            $customer->set_email('customer' . $customer_id . '@example.com');
            $customer->set_first_name('John');
            $customer->set_last_name('Doe');
            $customer->save();
        }
        return $customer->get_id();
    }

    /**
     * Creates a payment token for a customer.
     *
     * @param int $customer_id The customer ID.
     * @return WC_Payment_Token_CC The created payment token.
     * @throws \Exception
     */
    public function createAPaymentTokenForTheCustomer(int $customer_id = 1, $gateway_id = 'ppcp-gateway'): WC_Payment_Token_CC
    {
        $this->createCustomerIfNotExists($customer_id);

        $token = new WC_Payment_Token_CC();
        $token->set_token('test_token_' . uniqid()); // Unique token ID
        $token->set_gateway_id($gateway_id);
        $token->set_user_id($customer_id);

        // These fields are required for WC_Payment_Token_CC
        $token->set_card_type('visa'); // lowercase is often expected
        $token->set_last4('1234');
        $token->set_expiry_month('12');
        $token->set_expiry_year('2030'); // Missing expiry year in your original code

        $result = $token->save();

        if (!$result || is_wp_error($result)) {
            throw new \Exception('Failed to save payment token: ' .
                (is_wp_error($result) ? $result->get_error_message() : 'Unknown error'));
        }

        $saved_token = \WC_Payment_Tokens::get($token->get_id());
        if (!$saved_token || $saved_token->get_id() !== $token->get_id()) {
            throw new \Exception('Token was not saved correctly');
        }

        return $token;
    }

    /**
     * Helper method to create a subscription for testing.
     *
     * @param int $customer_id The customer ID
     * @param string $payment_method The payment method
     * @param string $sku
     * @return WC_Subscription
     * @throws \WC_Data_Exception
     */
    public function createSubscription(int $customer_id = 1, string $payment_method = 'ppcp-gateway', $sku = 'DUMMY SUB SKU'): WC_Subscription
    {
        $product_id = wc_get_product_id_by_sku($sku);

        $order = $this->getConfiguredOrder(
            $this->customer_id,
            $payment_method,
            ['subscription']
        );
        $subscription = new WC_Subscription();
        $subscription->set_customer_id($customer_id);
        $subscription->set_payment_method($payment_method);
        $subscription->set_status('active');
        $subscription->set_parent_id($order->get_id());
        $subscription->set_billing_period('month');
        $subscription->set_billing_interval(1);

        // Add a product to the subscription
        $subscription_item = new WC_Order_Item_Product();
        $subscription_item->set_props([
            'product_id' => $product_id,
            'quantity' => 1,
            'subtotal' => 10,
            'total' => 10,
        ]);
        $subscription->add_item($subscription_item);
        $subscription->set_date_created(current_time('mysql'));
        $subscription->set_start_date(current_time('mysql'));
        $subscription->set_next_payment_date(date('Y-m-d H:i:s', strtotime('+1 month', current_time('timestamp'))));
        $subscription->save();

        return $subscription;
    }

    /**
     * Creates a renewal order for testing
     *
     * @param int $customer_id
     * @param string $gateway_id
     * @param int $subscription_id
     * @return WC_Order
     */
    protected function createRenewalOrder(int $customer_id, string $gateway_id, int $subscription_id): WC_Order
    {
        $renewal_order = $this->getConfiguredOrder(
            $customer_id,
            $gateway_id,
            ['subscription'],
            [],
            false
        );
        $renewal_order->update_meta_data('_subscription_renewal', $subscription_id);
        $renewal_order->update_meta_data('_subscription_renewal', $subscription_id);
        $renewal_order->save();

        return $renewal_order;
    }
}
