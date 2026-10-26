<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Gateway;

use Automattic\WooCommerce\StoreApi\Legacy;
use Automattic\WooCommerce\StoreApi\RoutesController;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;
use WC_Order_Item_Fee;

/**
 * The fee on an order that is paid later, on the pay-for-order page: it follows the method the
 * order is finally paid with, not the one it was placed with. Both entry points WooCommerce offers
 * for that page are driven for real: the classic form handler and the Store API order-pay route.
 *
 * SurchargeAtCheckoutTest covers the cart; SurchargeHandlerTest (functional) the AJAX preview.
 *
 * @group integration
 * @group Surcharge
 */
class SurchargeOnOrderPayTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const PLACED_WITH = 'mollie_wc_gateway_ideal';
    private const FEE_LABEL = 'Gateway Fee';
    private const FEE_ON_PLACING = '10.00';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->addTestFilter('wc_tax_enabled', '__return_false');
    }

    public function tearDown(): void
    {
        global $wp;

        unset($_POST['woocommerce_pay'], $_POST['payment_method'], $_REQUEST['_wpnonce'], $_GET['key'], $wp->query_vars['order-pay']);
        if (function_exists('wc_clear_notices') && WC()->session) {
            wc_clear_notices();
        }
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @scenario an order placed with a fee is paid with another method
     *   Given an unpaid order carrying the 10.00 fee of the method it was placed with
     *   And the method it is paid with has a fee of its own, none, or is above the order limit
     *   When the shopper pays the order through the pay-for-order page
     *   Then the order carries only the fee of the method it is paid with, and the total follows
     *   And the payment created at Mollie asks for that total
     *
     * @test
     * @dataProvider paymentsOfAPlacedOrder
     * @covers \Mollie\WooCommerce\Shared\GatewaySurchargeHandler::recalculateSurchargeOnPayAction
     * @covers \Mollie\WooCommerce\Shared\GatewaySurchargeHandler::recalculateSurchargeOnStoreApiOrderPay
     *
     * @param array<string, string> $settings
     * @param array<int, string> $fees
     */
    public function it_charges_the_fee_of_the_method_the_order_is_paid_with(string $entryPoint, string $method, array $settings, array $fees): void
    {
        // Given
        $this->setGatewaySettingsForTest($method, array_merge(['enabled' => 'yes'], $settings));
        $order = $this->orderPlacedWithAFee();
        $goods = (float) $order->get_total() - (float) self::FEE_ON_PLACING;
        $expectedTotal = $this->decimal($goods + array_sum(array_map('floatval', $fees)));

        // When
        $this->{$entryPoint}($order, 'mollie_wc_gateway_' . $method);

        // Then
        $paid = $this->fresh($order);
        $this->assertSame($fees, $this->gatewayFeesOf($paid));
        $this->assertSame($expectedTotal, $this->decimal((float) $paid->get_total()));
        $created = $this->fakeMollie()->requests('POST', 'payments');
        $this->assertCount(1, $created, 'Paying the order creates one payment at Mollie.');
        $this->assertSame($expectedTotal, $created[0]['body']['amount']['value']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>, 3: array<int, string>}>
     */
    public function paymentsOfAPlacedOrder(): array
    {
        $rows = [];
        foreach (['the pay-for-order form' => 'payThroughTheForm', 'the Store API order-pay route' => 'payThroughTheStoreApi'] as $name => $entryPoint) {
            $rows["a method with a fee of its own, through $name"] = [$entryPoint, 'creditcard', $this->settings('fixed_fee', '5', '0', '0'), ['5.00']];
            $rows["a method without a fee, through $name"] = [$entryPoint, 'paypal', $this->settings('no_fee', '0', '0', '0'), []];
            $rows["an order above the method's limit, through $name"] = [$entryPoint, 'creditcard', $this->settings('percentage', '0', '10', '0.01'), []];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function settings(string $type, string $fixedFee, string $percentage, string $maximumLimit): array
    {
        return [
            'payment_surcharge' => $type,
            'fixed_fee' => $fixedFee,
            'percentage' => $percentage,
            'maximum_limit' => $maximumLimit,
            'surcharge_limit' => '0',
        ];
    }

    /**
     * An unpaid order placed with iDEAL at a time iDEAL carried a fee of 10.00, as the checkout leaves it.
     */
    private function orderPlacedWithAFee(): WC_Order
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_gatewayFeeLabel', self::FEE_LABEL);
        // The pay-page listeners are attached on init; this boot is the only one heard.
        $this->removePluginListenersOfEarlierBoots();
        $this->container = $this->bootExpressOwning(['init']);
        do_action('init');
        // The order belongs to the fixture customer; the Store API lets only its owner pay it.
        $this->actAsCustomer($this->customer_id);

        $order = $this->pendingOrder(self::PLACED_WITH);
        $fee = new WC_Order_Item_Fee();
        $fee->set_name(self::FEE_LABEL);
        $fee->set_amount(self::FEE_ON_PLACING);
        $fee->set_total(self::FEE_ON_PLACING);
        $fee->set_tax_status('taxable');
        $order->add_item($fee);
        $order->calculate_totals();
        $order->save();
        $order = $this->fresh($order);
        $this->assertSame([self::FEE_ON_PLACING], $this->gatewayFeesOf($order), 'The order starts with the fee it was placed with.');

        return $order;
    }

    /**
     * The classic pay-for-order form, submitted as WooCommerce's own handler reads it: it checks the
     * nonce and the order key itself, then hands the order to the gateway, which redirects to Mollie
     * and exits. The redirect is stopped with an Error: the handler turns any Exception into a notice.
     */
    private function payThroughTheForm(WC_Order $order, string $gatewayId): void
    {
        global $wp;

        $_POST['woocommerce_pay'] = '1';
        $_POST['payment_method'] = $gatewayId;
        $_REQUEST['_wpnonce'] = wp_create_nonce('woocommerce-pay');
        $_GET['key'] = $order->get_order_key();
        $wp->query_vars['order-pay'] = (string) $order->get_id();
        $this->addTestFilter('wp_redirect', static function ($location): void {
            throw new \Error('Redirect to ' . $location);
        }, PHP_INT_MAX, 1);

        $buffers = ob_get_level();
        try {
            \WC_Form_Handler::pay_action();
            $this->fail('The form handler must redirect to Mollie: ' . wc_print_notices(true));
        } catch (\Error $redirect) {
            $this->assertStringContainsString('mollie.com/checkout', $redirect->getMessage());
        } finally {
            while (ob_get_level() > $buffers) {
                ob_end_clean();
            }
        }
    }

    /**
     * The Store API order-pay route (POST /wc/store/v1/checkout/{id}), as the block pay-for-order
     * page calls it. The route checks that the order needs payment and sets the method on it.
     */
    private function payThroughTheStoreApi(WC_Order $order, string $gatewayId): void
    {
        $this->registerStoreApiRoutesClearedByBootExpress();
        $this->addTestFilter('woocommerce_store_api_disable_nonce_check', '__return_true');

        $response = $this->restRequest('POST', '/wc/store/v1/checkout/' . $order->get_id(), [
            'billing_address' => $this->billing(),
            'payment_method' => $gatewayId,
            'payment_data' => [],
        ]);

        $this->assertSame(200, $response->get_status(), 'The route must accept the payment: ' . wp_json_encode($response->get_data()));
    }

    private function registerStoreApiRoutesClearedByBootExpress(): void
    {
        add_action('rest_api_init', static function (): void {
            StoreApi::container()->get(Legacy::class)->init();
            StoreApi::container()->get(RoutesController::class)->register_all_routes();
        });
        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();
    }

    /**
     * @return array<int, string> The amount of every "Gateway Fee" line on the order.
     */
    private function gatewayFeesOf(WC_Order $order): array
    {
        $amounts = [];
        foreach ($order->get_fees() as $fee) {
            if ($fee->get_name() === self::FEE_LABEL) {
                $amounts[] = $this->decimal((float) $fee->get_total());
            }
        }

        return $amounts;
    }
}
