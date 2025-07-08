<?php
# -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mockery;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware;
use Mollie\WooCommerce\Payment\Request\Strategies\OrderRequestStrategy;
use Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\WC_Order_Item_Product;
use Mollie\WooCommerceTests\Stubs\WC_Settings_API;
use Mollie\WooCommerceTests\Stubs\WC_Product;
use Mollie\WooCommerceTests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Order;

use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Plugin_Test
 */
class OrderRequestStrategyTest extends TestCase
{
    /**
     * @var HelperMocks
     */
    private $helperMocks;

    public function setUp(): void
    {
        $_POST = [];
        parent::setUp();

        when('__')->returnArg(1);

        $this->helperMocks = new HelperMocks();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_createRequest_returnsFailure_ifGatewayMissingOrNotMollie()
    {
        when('wc_get_payment_gateway_by_order')->justReturn(null);


        stubs([
                  'mollieWooCommerceIsMollieGateway' => false,
              ]);

        $dataHelper = $this->helperMocks->dataHelper();
        $settingsHelper = $this->helperMocks->settingsHelper();

        $middlewareHandler = new MiddlewareHandler([]);
        $strategyClass = OrderRequestStrategy::class;
        /** @var RequestStrategyInterface $strategy */
        $strategy = new $strategyClass($dataHelper, $settingsHelper, $middlewareHandler);

        $order = Mockery::mock(WC_Order::class);

        $result = $strategy->createRequest($order, 'some-customer-id');
        $this->assertEquals(['result' => 'failure'], $result, 'Should return failure if no gateway is found.');
    }

    public function test_createRequest_returnsFailure_ifNotMollieGateway()
    {
        $nonMollieGateway = new \stdClass();
        $nonMollieGateway->id = 'some_other_gateway';

        when('wc_get_payment_gateway_by_order')->justReturn($nonMollieGateway);
        stubs([
                  'mollieWooCommerceIsMollieGateway' => false,
              ]);

        $dataHelper = $this->helperMocks->dataHelper();
        $settingsHelper = $this->helperMocks->settingsHelper();
        $middlewareHandler = new MiddlewareHandler([]);
        $strategyClass = OrderRequestStrategy::class;
        $strategy = new $strategyClass($dataHelper, $settingsHelper, $middlewareHandler);
        $order = Mockery::mock(WC_Order::class);

        $result = $strategy->createRequest($order, 'some-customer-id');
        $this->assertEquals(['result' => 'failure'], $result, 'Should return failure if gateway is not Mollie.');
    }

    public function test_createRequest_buildsExpectedData_forValidMollieGateway()
    {
        $mollieGateway = new \stdClass();
        $mollieGateway->id = 'mollie_ideal';

        when('wc_get_payment_gateway_by_order')->justReturn($mollieGateway);
        stubs([
                  'mollieWooCommerceIsMollieGateway' => true,
              ]);

        $dataHelper = $this->helperMocks->dataHelper();
        $settingsHelper = $this->helperMocks->settingsHelper();
        $order = Mockery::mock(WC_Order::class);
        $middleware = Mockery::mock(OrderLinesMiddleware::class);
        $middleware->shouldReceive('__invoke')->andReturnUsing(function ($data) {
            $data['decorated'] = true;
            return $data;
        });

        $middlewareHandler = new MiddlewareHandler([$middleware]);


        $strategyClass = OrderRequestStrategy::class;
        $strategy = new $strategyClass($dataHelper, $settingsHelper, $middlewareHandler);


        $order->shouldReceive('get_id')->andReturn(1234);
        $order->shouldReceive('get_total')->andReturn(99.99);
        $order->shouldReceive('get_order_number')->andReturn('1001');
        $order->shouldReceive('get_currency')->andReturn('EUR');

        $result = $strategy->createRequest($order, 'cust_abc123');


        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('locale', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('orderNumber', $result);

        $this->assertEquals('ideal', $result['method']);
        $this->assertEquals('en_US', $result['locale'], 'Should get locale from settings');
        $this->assertEquals('EUR', $result['amount']['currency']);
        $this->assertEquals('99.99', $result['amount']['value'], 'Should reflect the formatted total');

        $this->assertEquals(1234, $result['metadata']['order_id']);
        $this->assertEquals('1001', $result['orderNumber']);

        $this->assertArrayHasKey('decorated', $result, 'Decorator was applied');
        $this->assertTrue($result['decorated'], 'Decorator set its key to true');
    }
    public $products = [
        '1' => [
            'id' => 1,
            'name' => 'product 1',
            'price' => '11.123'
        ],
        '2' => [
            'id' => 2,
            'name' => 'product 2',
            'price' => '9.00'
        ],
        '3' => [
            'id' => 3,
            'name' => 'product 3',
            'price' => '26.1234'
        ],
        '4' => [
            'id' => 4,
            'name' => 'product 4',
            'price' => '30.01'
        ]
    ];

    /**
     * GIVEN A PAYMENT REQUEST
     * WHEN THE TOTAL AMOUNT HAS DECIMALS
     * THEN THE TOTAL AMOUNT HAS TO BE EQUAL TO THE SUM OF THE LINES
     *
     *
     * @test
     * @group skip
     */
    public function processPayment_decimals_and_taxes_request_no_mismatch()
    {
        $testDataSet = $this->generateTestDataSet();

        foreach ($testDataSet as $order) {
            $this->executeTest($order);
        }
    }

    private function generateTestDataSet(): array
    {
        $products = $this->products;
        return [
            $this->wcOrder('76.25', [
                $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1),
                $this->wcOrderItem($products['2']['id'], $products['2']['name'], $products['2']['price'], 1),
                $this->wcOrderItem($products['3']['id'], $products['3']['name'], $products['3']['price'], 1),
                $this->wcOrderItem($products['4']['id'], $products['4']['name'], $products['4']['price'], 1)
            ]),
            $this->wcOrder('46.2464', [
                $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1),
                $this->wcOrderItem($products['2']['id'], $products['2']['name'], $products['2']['price'], 1),
                $this->wcOrderItem($products['3']['id'], $products['3']['name'], $products['3']['price'], 1)
            ]),
             $this->wcOrder('20.1234', [
                 $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1),
                 $this->wcOrderItem($products['2']['id'], $products['2']['name'], $products['2']['price'], 1)
             ]),
             $this->wcOrder('11.123', [
                 $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1)
             ])
        ];
    }

    public function executeTest($order)
    {
        $customerId = 1;

        // Mock product retrieval so each product ID corresponds to a product with the given price
        $wrapperMock = $this->createMock(WC_Product::class);
        $callback = function ($productId) {
            $price = $this->products[$productId]['price'];
            return $this->wcProduct($productId, $price);
        };
        $wrapperMock->method('getProduct')->willReturnCallback($callback);
        $apiClientMock = $this->createConfiguredMock(
            MollieApiClient::class,
            []
        );
        $orderLines = new OrderLines($this->helperMocks->dataHelper($apiClientMock), $this->helperMocks->pluginId());
        $linesDecorator = new OrderLinesMiddleware($orderLines, 'no_category');
        $paymentGateway = $this->helperMocks->genericPaymentGatewayMock();
        when('wc_get_payment_gateway_by_order')->justReturn($paymentGateway);

        stubs([
                  'add_query_arg' => 'https://webshop.example.org/wc-api/mollie_return?order_id=1&key=wc_order_hxZniP1zDcnM8',
                  'WC' => $this->wooCommerce(),
                  'get_option' => ['enabled' => false],
                  'wc_get_product' => $wrapperMock,
                  'wc_clean' => false,
                  'wp_parse_url' => null,
                  'wp_strip_all_tags' => null,
              ]);



        $strategy = new OrderRequestStrategy(
            $this->helperMocks->dataHelper($apiClientMock),
            $this->helperMocks->settingsHelper(),
            [$linesDecorator]
        );
        $createRequestResult = $strategy->createRequest($order, (string) $customerId);

        $this->assertArrayHasKey('amount', $createRequestResult, 'createRequest should include amount');
        $this->assertArrayHasKey('value', $createRequestResult['amount'], 'createRequest->amount should have value');
        $this->assertArrayHasKey('currency', $createRequestResult['amount'], 'createRequest->amount should have currency');
        $this->assertArrayHasKey('method', $createRequestResult, 'createRequest should include method');
        $this->assertArrayHasKey('locale', $createRequestResult, 'createRequest should include locale');
        $this->assertArrayHasKey('metadata', $createRequestResult, 'createRequest should include metadata');
        $this->assertArrayHasKey('orderNumber', $createRequestResult, 'createRequest should include orderNumber');

        $expectedResult = $this->noMismatchError($createRequestResult);
        $this->assertTrue($expectedResult);
    }

    private function wcProduct($productId, $price)
    {

        $item = $this->createConfiguredMock(
            WC_Product::class,
            [
                'get_price' => $price,
                'get_id'=>$productId,
                'get_type' => 'simple',
                'needs_shipping' => true,
                'get_sku'=>5,
                'is_taxable'=>true
            ]
        );

        return $item;
    }

    protected function mollieGateway($paymentMethodName, $testee, $isSepa = false, $isSubscription = false)
    {
        return $this->helperMocks->mollieGatewayBuilder($paymentMethodName, $isSepa, $isSubscription, [], $testee);
    }


    /**
     *
     * @param $total
     * @param $items
     * @return (object&MockObject)|MockObject|\WC_Order|(\WC_Order&object&MockObject)|(\WC_Order&MockObject)
     */
    private function wcOrder($total, $items)
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => 1,
                'get_order_key' => 'wc_order_hxZniP1zDcnM8',
                'get_total' => $total,
                'get_items' => $items,
                'get_billing_first_name' => 'billingggivenName',
                'get_billing_last_name' => 'billingfamilyName',
                'get_billing_email' => 'billingemail',
                'get_shipping_first_name' => 'shippinggivenName',
                'get_shipping_last_name' => 'shippingfamilyName',
                'get_billing_address_1' => 'shippingstreetAndNumber',
                'get_billing_address_2' => 'billingstreetAdditional',
                'get_billing_postcode' => 'billingpostalCode',
                'get_billing_city' => 'billingcity',
                'get_billing_state' => 'billingregion',
                'get_billing_country' => 'billingcountry',
                'get_billing_phone' => '+34345678900',
                'get_billing_company' => 'billingorganizationName',
                'get_shipping_address_1' => 'shippingstreetAndNumber',
                'get_shipping_address_2' => 'shippingstreetAdditional',
                'get_shipping_postcode' => 'shippingpostalCode',
                'get_shipping_city' => 'shippingcity',
                'get_shipping_state' => 'shippingregion',
                'get_shipping_country' => 'shippingcountry',
                'get_shipping_methods' => false,
                'get_order_number' => 1,
                'get_payment_method' => 'mollie_wc_gateway_ideal',
                'get_currency' => 'EUR',
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce()
    {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [
                'api_request_url' => 'https://webshop.example.org/wc-api/mollie_return'
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrderItem($id, $name, $price, $quantity)
    {
        $item = new \WC_Order_Item_Product();

        $item['quantity'] = $quantity;
        $item['variation_id'] = null;
        $item['product_id'] = $id;
        $item['line_subtotal_tax'] = 0;
        $item['line_total'] = $price;
        $item['line_subtotal'] = $price;
        $item['line_tax'] = 0;
        $item['tax_status'] = '';
        $item['total'] = $price;
        $item['name'] = $name;

        return $item;
    }

    private function noMismatchError(array $arrayResult)
    {
        //array result total equals the sum of the lines
        $total = ($arrayResult['amount']['value']) * 1000;
        $lines = $arrayResult['lines'];
        $sum = 0.0;
        foreach ($lines as $line) {
            $lineValue = ($line['totalAmount']['value']) * 1000;
            $sum += $lineValue;
        }
        return $total == $sum;
    }
}



