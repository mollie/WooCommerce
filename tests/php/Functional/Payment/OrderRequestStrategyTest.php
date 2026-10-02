<?php
# -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware;
use Mollie\WooCommerce\Payment\Request\Strategies\OrderRequestStrategy;
use Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\WC_Settings_API;
use Mollie\WooCommerceTests\TestCase;
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
}
