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
     * @throws \WC_Data_Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->customer_id = $this->createCustomerIfNotExists();
        $this->createTestProducts();
        //$this->createTestCoupons();
    }

    public function tearDown(): void
    {
        // This cleans up everything created during tests
        //$this->cleanupTestData();
        parent::tearDown();
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
