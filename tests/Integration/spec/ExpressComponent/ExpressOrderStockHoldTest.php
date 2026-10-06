<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Automattic\WooCommerce\StoreApi\Legacy;
use Automattic\WooCommerce\StoreApi\RoutesController;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WC_Order;

/**
 * POST mollie/v1/express/order while an unpaid order holds the stock.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactory
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressOrderLifecycle
 */
class ExpressOrderStockHoldTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const PAYPAL_GATEWAY = 'mollie_wc_gateway_paypal';

    /**
     * @var array{manage_stock: bool, stock_quantity: ?int, stock_status: string}|null
     */
    private ?array $stockBeforeTest = null;

    /**
     * @var array<int, int>
     */
    private array $ordersBefore = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->ordersBefore = $this->allOrderIds();
    }

    public function tearDown(): void
    {
        $this->deleteHoldsSurvivingRollback(array_diff($this->allOrderIds(), $this->ordersBefore));
        if ($this->stockBeforeTest !== null) {
            $product = $this->simpleProduct();
            $product->set_manage_stock($this->stockBeforeTest['manage_stock']);
            $product->set_stock_quantity($this->stockBeforeTest['stock_quantity']);
            $product->set_stock_status($this->stockBeforeTest['stock_status']);
            $product->save();
            $this->stockBeforeTest = null;
        }
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: the shopper's own pending order does not block their express order
     *   Given the shopper's pending order holds the last stock
     *   When they submit express
     *   Then the express order takes the hold
     *
     * @test
     */
    public function it_creates_the_express_order_when_the_shoppers_own_pending_order_holds_the_last_stock(): void
    {
        $this->lastItemsInStock(2);
        $this->readyGuestCheckout();
        $this->startedSession();
        $earlier = $this->placeBlockCheckoutOrderThatRedirects();
        $this->assertSame(2, $this->unexpiredHeldQuantity($earlier), 'The block checkout must hold the stock.');
        $this->reloadCartAsTheNextRequestWould();

        $response = $this->startOrder();

        $this->assertAnsweredOk($response);
        $express = $this->theOnlyExpressOrder($earlier);
        $this->assertSame('pending', wc_get_order($earlier)->get_status(), 'The earlier order must stay as it was.');
        $this->assertSame(0, $this->unexpiredHeldQuantity($earlier));
        $this->assertSame(2, $this->unexpiredHeldQuantity($express->get_id()));
    }

    /**
     * Scenario: the classic checkout's pending order counts as the shopper's own
     *   Given only order_awaiting_payment names the pending order
     *   When they submit express
     *   Then the express order takes the hold
     *
     * @test
     */
    public function it_creates_the_express_order_when_the_own_pending_order_is_known_only_as_awaiting_payment(): void
    {
        $this->lastItemsInStock(2);
        $this->readyGuestCheckout();
        $this->startedSession();
        $earlier = $this->placeBlockCheckoutOrderThatRedirects();
        $this->asIfPlacedByClassicCheckout();
        $this->assertSame($earlier, absint(WC()->session->get('order_awaiting_payment')));
        $this->reloadCartAsTheNextRequestWould();

        $response = $this->startOrder();

        $this->assertAnsweredOk($response);
        $express = $this->theOnlyExpressOrder($earlier);
        $this->assertSame('pending', wc_get_order($earlier)->get_status());
        $this->assertSame(0, $this->unexpiredHeldQuantity($earlier));
        $this->assertSame(2, $this->unexpiredHeldQuantity($express->get_id()));
    }

    /**
     * Scenario: another shopper's hold is respected
     *   Given another shopper's pending order holds the last stock
     *   When this shopper submits express
     *   Then WooCommerce's out-of-stock reason is returned and the hold stays
     *
     * @test
     */
    public function it_refuses_with_woocommerces_reason_when_another_shopper_holds_the_last_stock(): void
    {
        $this->lastItemsInStock(2);
        $this->readyGuestCheckout();
        $theirs = $this->placeBlockCheckoutOrderThatRedirects();
        $this->theNextShopperWithTheSameCart();
        $this->startedSession();
        $before = $this->allOrderIds();

        $response = $this->startOrder();

        $data = (array) $response->get_data();
        $this->assertSame(409, $response->get_status(), 'Expected a refusal: ' . wp_json_encode($data));
        $this->assertFalse($data['ok'] ?? null);
        $this->assertSame('cart_invalid', $data['code'] ?? null);
        $message = (string) ($data['message'] ?? '');
        $this->assertStringContainsString($this->simpleProduct()->get_name(), $message, 'The reason must be WooCommerce\'s, naming the product.');
        $this->assertStringContainsString('stock', $message);
        $this->assertSame($before, $this->allOrderIds());
        $this->assertSame(2, $this->unexpiredHeldQuantity($theirs), 'Another shopper\'s hold must not be released.');
    }

    /**
     * Scenario: a shopper without an own order releases nobody's hold
     *   Given another shopper's pending order holds part of the stock
     *   When this shopper submits express
     *   Then both orders hold their stock
     *
     * @test
     */
    public function it_releases_no_other_hold_when_the_shopper_has_no_order_of_their_own(): void
    {
        $this->lastItemsInStock(4);
        $this->readyGuestCheckout();
        $theirs = $this->placeBlockCheckoutOrderThatRedirects();
        $this->theNextShopperWithTheSameCart();
        $this->startedSession();
        $this->assertSame(0, absint(WC()->session->get('order_awaiting_payment')));
        $this->assertSame(0, absint(WC()->session->get('store_api_draft_order')));

        $response = $this->startOrder();

        $this->assertAnsweredOk($response);
        $express = $this->theOnlyExpressOrder($theirs);
        $this->assertSame(2, $this->unexpiredHeldQuantity($express->get_id()));
        $this->assertSame(2, $this->unexpiredHeldQuantity($theirs), 'Another shopper\'s hold must not be released.');
    }

    /**
     * Scenario: a double submit reuses the express order and its hold
     *   Given the shopper's pending order holds the last stock
     *   When they submit express twice
     *   Then one express order holds the stock
     *
     * @test
     */
    public function it_reuses_the_express_order_and_keeps_its_hold_on_a_repeated_submit(): void
    {
        $this->lastItemsInStock(2);
        $this->readyGuestCheckout();
        $this->startedSession();
        $earlier = $this->placeBlockCheckoutOrderThatRedirects();
        $this->reloadCartAsTheNextRequestWould();

        $first = $this->startOrder();
        $second = $this->startOrder();

        $this->assertAnsweredOk($first);
        $this->assertAnsweredOk($second);
        $express = $this->theOnlyExpressOrder($earlier);
        $this->assertSame(2, $this->unexpiredHeldQuantity($express->get_id()));
        $this->assertSame(0, $this->unexpiredHeldQuantity($earlier));
    }

    /**
     * Scenario: an express order that does not come to be gives the hold back
     *   Given the shopper's pending order holds the last stock
     *   And the express order will be refused after WooCommerce created it
     *   When they submit express
     *   Then no express order is left
     *   And the pending order holds its stock again
     *
     * @test
     * @dataProvider ordersThatDoNotComeToBe
     */
    public function it_gives_the_hold_back_when_the_express_order_does_not_come_to_be(string $hook, callable $failure): void
    {
        $this->lastItemsInStock(2);
        $this->readyGuestCheckout();
        $this->startedSession();
        $earlier = $this->placeBlockCheckoutOrderThatRedirects();
        $this->reloadCartAsTheNextRequestWould();
        $this->addTestFilter($hook, $failure, 10, 1);

        $response = $this->startOrder();

        $this->assertFalse(((array) $response->get_data())['ok'] ?? null);
        $this->assertSame([$earlier], array_values(array_diff($this->allOrderIds(), $this->ordersBefore)), 'No express order may be left.');
        $this->assertSame('pending', wc_get_order($earlier)->get_status());
        $this->assertSame(2, $this->unexpiredHeldQuantity($earlier));
    }

    /**
     * @return array<string, array{0: string, 1: callable}>
     */
    public function ordersThatDoNotComeToBe(): array
    {
        return [
            'its total is not the amount of the session' => [
                'woocommerce_checkout_create_order',
                static function (WC_Order $order): void {
                    $order->set_total((string) ((float) $order->get_total() + 0.01));
                },
            ],
            'WooCommerce throws after saving it' => [
                'woocommerce_checkout_order_created',
                static function (): void {
                    throw new \RuntimeException('Simulated failure after the order was saved.');
                },
            ],
            'stamping it fails' => [
                'woocommerce_before_order_object_save',
                static function (WC_Order $order): void {
                    if ($order->get_created_via() === 'mollie_express') {
                        throw new \RuntimeException('Simulated failure while stamping the order.');
                    }
                },
            ],
        ];
    }

    private function placeBlockCheckoutOrderThatRedirects(): int
    {
        $this->registerStoreApiRoutesClearedByBootExpress();
        $this->addTestFilter('woocommerce_store_api_disable_nonce_check', '__return_true');
        $toTheHostedPage = static function ($context, &$result): void {
            $result->set_status('success');
            $result->set_redirect_url('https://example.test/mollie-hosted-page');
        };
        add_action('woocommerce_rest_checkout_process_payment_with_context', $toTheHostedPage, 1, 2);
        try {
            $response = $this->restRequest('POST', '/wc/store/v1/checkout', [
                'billing_address' => $this->billing(),
                'shipping_address' => array_merge($this->shipping('LU'), ['phone' => '']),
                'payment_method' => self::PAYPAL_GATEWAY,
                'payment_data' => [],
            ]);
        } finally {
            remove_action('woocommerce_rest_checkout_process_payment_with_context', $toTheHostedPage, 1);
        }

        $data = (array) $response->get_data();
        $this->assertSame(200, $response->get_status(), 'The block checkout must place the order: ' . wp_json_encode($data));
        $order = wc_get_order((int) ($data['order_id'] ?? 0));
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertSame('pending', $order->get_status());

        return $order->get_id();
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

    private function asIfPlacedByClassicCheckout(): void
    {
        WC()->session->set('store_api_draft_order', 0);
    }

    private function theNextShopperWithTheSameCart(): void
    {
        $this->newShopper();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
    }

    private function theOnlyExpressOrder(int $notThis): WC_Order
    {
        $created = array_values(array_diff($this->allOrderIds(), $this->ordersBefore, [$notThis]));
        $this->assertCount(1, $created, 'Exactly one express order must be created.');
        $order = wc_get_order($created[0]);
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('mollie_express', $order->get_created_via());

        return $order;
    }

    private function unexpiredHeldQuantity(int $orderId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(stock_quantity), 0) FROM {$wpdb->wc_reserved_stock} WHERE order_id = %d AND expires > NOW()",
            $orderId
        ));
    }

    /**
     * @param array<int, int> $orderIds
     */
    private function deleteHoldsSurvivingRollback(array $orderIds): void
    {
        global $wpdb;

        foreach ($orderIds as $orderId) {
            $wpdb->delete($wpdb->wc_reserved_stock, ['order_id' => (int) $orderId]);
        }
    }

    private function lastItemsInStock(int $quantity): void
    {
        $product = $this->simpleProduct();
        $this->stockBeforeTest = [
            'manage_stock' => $product->get_manage_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
        ];
        $product->set_manage_stock(true);
        $product->set_stock_quantity($quantity);
        $product->set_stock_status('instock');
        $product->save();
    }

    private function reloadCartAsTheNextRequestWould(): void
    {
        $contents = WC()->cart->get_cart();
        foreach ($contents as $key => $item) {
            $contents[$key]['data'] = wc_get_product((int) ($item['variation_id'] ?: $item['product_id']));
        }
        WC()->cart->set_cart_contents($contents);
    }

    private function simpleProduct(): \WC_Product
    {
        $product = wc_get_product((int) wc_get_product_id_by_sku(ProductPresets::get()['simple']['sku']));
        $this->assertInstanceOf(\WC_Product::class, $product);

        return $product;
    }
}
