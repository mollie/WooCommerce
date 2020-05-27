<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC;

use Brain\Monkey\Expectation\Exception\ExpectationArgsRequired;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Plugin;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_MockObject_RuntimeException;
use RuntimeException;
use stdClass;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use Faker;

class Mollie_WC_Plugin_Test extends TestCase
{
    /**
     * @param $allowed
     * @param $expected
     * @throws ExpectationArgsRequired
     * @throws PHPUnit_Framework_MockObject_RuntimeException
     * @dataProvider allowingDataProvider
     */
    public function testMaybeDisableApplePayGateway($allowed, $expected)
    {
        /*
         * Setup stubs
         */
        $postData = Mollie_WC_Plugin::APPLE_PAY_METHOD_ALLOWED_KEY . "={$allowed}";
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * Mocks
         */
        $wooCommerceSession = $this->mockWooCommerceSession();

        /*
         * Expect to retrieve the WooCommerce Session
         * Wc Session is used to store the availability of Apple Pay payment method
         */
        expect('mollieWooCommerceSession')
            ->once()
            ->andReturn($wooCommerceSession);

        /*
         * Check Preconditions
         */
        expect('filter_input')
            ->once()
            ->with(INPUT_GET, 'wc-api', FILTER_SANITIZE_STRING)
            ->andReturn(false);
        expect('is_admin')->once()->andReturn(false);
        expect('wp_doing_ajax')->once()->andReturn(true);
        expect('doing_action')->once()->with('woocommerce_payment_gateways')->andReturn(true);

        $wooCommerceSession
            ->expects($this->once())
            ->method('get')
            ->with(Mollie_WC_Plugin::APPLE_PAY_METHOD_ALLOWED_KEY, false)
            ->willReturn(false);

        /*
         * Expect to have the request to remove the apple pay method
         */
        expect('filter_input')
            ->once()
            ->with(INPUT_POST, Mollie_WC_Plugin::POST_DATA_KEY, FILTER_SANITIZE_STRING)
            ->andReturn($postData);

        $wooCommerceSession
            ->expects($allowed ? $this->once() : $this->never())
            ->method('set')
            ->with(Mollie_WC_Plugin::APPLE_PAY_METHOD_ALLOWED_KEY, true);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::maybeDisableApplePayGateway($gateways);

        self::assertEquals($expected, in_array('Mollie_WC_Gateway_Applepay', $result, true));
    }

    /**
     * Test Apple Pay Gateway is not filtered because of is WC Api Request
     * precondition fails.
     * @throws ExpectationArgsRequired
     */
    public function testMaybeDisableApplePayGatewayDoesNotFilterBecauseWcApiRequest()
    {
        /*
         * Stubs
         */
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * Mocks
         */
        $wooCommerceSession = $this->mockWooCommerceSession();

        /*
         * Expect to retrieve appropriate values for test.
         */
        expect('mollieWooCommerceSession')
            ->once()
            ->andReturn($wooCommerceSession);

        expect('filter_input')
            ->once()
            ->with(INPUT_GET, 'wc-api', FILTER_SANITIZE_STRING)
            ->andReturn(true);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::maybeDisableApplePayGateway($gateways);

        self::assertEquals($result, $gateways);
    }

    /**
     * Test Apple Pay Gateway is not filtered because wrong current action
     * @throws ExpectationArgsRequired
     */
    public function testMaybeDisableApplePayGatewayDoesNotFilterBecauseWrongCurrentAction()
    {
        /*
         * Stubs
         */
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * Mocks
         */
        $wooCommerceSession = $this->mockWooCommerceSession();

        /*
         * Expect to retrieve appropriate values for test.
         */
        expect('mollieWooCommerceSession')
            ->once()
            ->andReturn($wooCommerceSession);

        /*
         * Check Precondition
         */
        expect('doing_action')
            ->with('woocommerce_payment_gateways')
            ->andReturn(false);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::maybeDisableApplePayGateway($gateways);

        self::assertEquals($result, $gateways);
    }

    /**
     * Test Apple Pay Gateway is not filtered because Not an ajax request
     * @throws ExpectationArgsRequired
     */
    public function testMaybeDisableApplePayGatewayDoesNotFilterBecauseNotAjaxRequest()
    {
        /*
         * Stubs
         */
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * Mocks
         */
        $wooCommerceSession = $this->mockWooCommerceSession();

        /*
         * Expect to retrieve appropriate values for test.
         */
        expect('mollieWooCommerceSession')
            ->once()
            ->andReturn($wooCommerceSession);

        /*
         * Check Precondition
         */
        expect('doing_action')
            ->with('woocommerce_payment_gateways')
            ->andReturn(true);

        expect('wp_doing_ajax')
            ->once()
            ->andReturn(false);
        expect('is_wc_endpoint_url')
            ->once()
            ->andReturn(false);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::maybeDisableApplePayGateway($gateways);

        self::assertEquals($result, $gateways);
    }

    /*
     * Test Apple Pay Gateway is not filtered because it's admin context
     */
    public function testMaybeDisableApplePayGatewayDoesNotFilterBecauseAdminContext()
    {
        /*
         * Stubs
         */
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * Mocks
         */
        $wooCommerceSession = $this->mockWooCommerceSession();

        /*
         * Expect to retrieve appropriate values for test.
         */
        expect('mollieWooCommerceSession')
            ->once()
            ->andReturn($wooCommerceSession);

        /*
         * Check Precondition
         */
        expect('doing_action')
            ->with('woocommerce_payment_gateways')
            ->andReturn(true);

        expect('wp_doing_ajax')
            ->once()
            ->andReturn(true);

        expect('is_admin')
            ->andReturn(true);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::maybeDisableApplePayGateway($gateways);

        self::assertEquals($result, $gateways);
    }

    /**
     * Data Provider for testMaybeDisableApplePayGateway
     *
     * @return array
     */
    public function allowingDataProvider()
    {
        return [
            [1, true],
            [0, false],
        ];
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function mockWooCommerceSession()
    {
        $mock = $this
            ->getMockBuilder('\\WC_Session')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'set'])
            ->getMock();

        return $mock;
    }

    /**
     * Given orderByRequest is called
     * when id and key are valid in the request
     * then we get an order by id
     *
     * @test
     */
    public function orderByRequest_returnOrder_byId()
    {
        /*
         * Setup Stubs
         */
        $dataHelper = $this->mockDataHelper();
        $order = $this->mockOrder();

        //functions called
        expect('mollieWooCommerceGetDataHelper')
            ->andReturn($dataHelper);

        $dataHelper
            ->method('getWcOrder')
            ->willReturn($order);
        $order
            ->method('key_is_valid')
            ->willReturn(true);
        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::orderByRequest();
        self::assertEquals($order, $result);
    }

    /**
     * Given orderByRequest is called
     * when key is valid but not id in the request
     * then we get an order by key
     *
     * @test
     */
    public function orderByRequest_returnOrder_byKey()
    {
        /*
         * Setup Stubs
         */
        $dataHelper = $this->mockDataHelper();
        $order = $this->mockOrder();
        $faker = Faker\Factory::create();
        $id = null;
        $key = $faker->word;

        //functions called
        expect('mollieWooCommerceGetDataHelper')
            ->andReturn($dataHelper);
        when('wc_get_order_id_by_order_key')
            ->justReturn($key);

        //so first time id retrieving fails
        //and we pass key to retrieve order
        $dataHelper
            ->method('getWcOrder')
            ->withConsecutive([$id], [$key])
            ->willReturn(false, $order);

        $order
            ->method('key_is_valid')
            ->willReturn(true);
        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::orderByRequest();
        self::assertEquals($order, $result);
    }

    /**
     * Given orderByRequest is called
     * when id nor key are valid
     * then we get an exception
     *
     * @test
     *
     * @param $returned wether we get or not the order
     * @param $message  the message of the runtimeException
     * @param $code     the code of the runtimeException
     *
     * @dataProvider orderByRequestDataProvider
     * @expectedException RuntimeException
     */
    public function orderByRequest_returnException($returned, $message, $code){
        /*
         * Setup Stubs
         */
        $dataHelper = $this->mockDataHelper();
        $order = $this->mockOrder();
        $id = '';
        $key = '';
        if ($returned) {
            $returned = $order;
        }

        //functions called
        expect('mollieWooCommerceGetDataHelper')
            ->andReturn($dataHelper);
        when('wc_get_order_id_by_order_key')
            ->justReturn($key);

        // retrieving fails
        $dataHelper
            ->method('getWcOrder')
            ->withConsecutive([$id], [$key])
            ->willReturn(false, $returned);

        $order
            ->method('key_is_valid')
            ->willReturn(false);
        /*
         * Execute Test
         */
        Mollie_WC_Plugin::orderByRequest();

        self::expectExceptionMessage($message);
        self::expectExceptionCode($code);
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function mockOrder()
    {
        $mock = $this->getMockBuilder(WC_Order::class)
            ->disableOriginalConstructor()
            ->setMethods(['key_is_valid'])
            ->getMock();

        return $mock;
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function mockDataHelper()
    {
        $mock = $this->getMockBuilder(Mollie_WC_Helper_Data::class)
            ->disableOriginalConstructor()
            ->setMethods(['getWcOrder', 'getWcPaymentGatewayByOrder'])
            ->getMock();

        return $mock;
    }

    /**
     * Data Provider for orderByRequest
     *
     * @return array
     */
    public function orderByRequestDataProvider()
    {
        return [
            [
                false,
                "Could not find order by order Id ",
                404
            ],
            [
                true,
                "Invalid key given. Key  does not match the order id: ",
                401
            ],
        ];
    }
}
