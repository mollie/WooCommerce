<?php # -*- coding: utf-8 -*-

use Brain\Monkey\Expectation\Exception\ExpectationArgsRequired;
use function Brain\Monkey\Functions\expect;
use MollieTests\TestCase;

/**
 * Class Mollie_WC_Plugin_Test
 */
class Mollie_WC_Plugin_Test extends TestCase
{
    /**
     * Test Disable Apple Pay Gateway
     *
     * @dataProvider allowingDataProvider
     * @param $allowed
     * @param $expected
     * @throws PHPUnit_Framework_MockObject_RuntimeException
     * @throws ExpectationArgsRequired
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
}
