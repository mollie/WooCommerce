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
use stdClass;
use function Brain\Monkey\Functions\expect;

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
     * Test paymentMethodsImages returns array ordered by id of payment method to access images directly
     *
     * @test
     */
    public function paymentMethodsImagesArrayOrderedById()
    {
        /*
         * Setup stubs
         */
        $links = new \stdClass();
        $methods = new MethodCollection(13, $links);
        $client = $this
            ->buildTesteeMock(
                MollieApiClient::class,
                [],
                []
            )
            ->getMock();
        $methodIdeal = new Method($client);
        $methodIdeal->id = "ideal";
        $methodIdeal->image = json_decode('{
                            "size1x": "https://mollie.com/external/icons/payment-methods/ideal.png",
                            "size2x": "https://mollie.com/external/icons/payment-methods/ideal%402x.png",
                            "svg": "https://mollie.com/external/icons/payment-methods/ideal.svg"
                            }');
        $methods[] = $methodIdeal;
        $methods_cleaned = array();
        foreach ( $methods as $method ) {
            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        $paymentMethodsListResult = [
            "ideal" => [
                "id" => "ideal",
                "description"=> NULL,
                "minimumAmount"=> NULL,
                "maximumAmount" => NULL,
                "image" => $methodIdeal->image,
                "issuers" => NULL,
                "pricing"=> NULL,
                "_links"=> NULL
            ]
        ];

        /*
         * Expect to call getApiMethods() function and return a mock of one method
         */
        expect('getApiMethods')
            ->once()
            ->withNoArgs()
            ->andReturn($methods);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::paymentMethodsImages();

        self::assertEquals($paymentMethodsListResult,$result );
    }

    /**
     * Test paymentMethodsImages returns array ordered by id of payment method to access images directly
     *
     * @test
     */
    public function paymentMethodsImagesReturnsEmptyArrayIfApiFails()
    {

        /*
         * Expect to call getApiMethods() function and return false
         */
        $emptyArr = [];
        expect('getApiMethods')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::paymentMethodsImages();

        self::assertEquals($emptyArr,$result );

        /*
        * Expect to call getApiMethods() function and return empty array
        */
        expect('getApiMethods')
            ->once()
            ->withNoArgs()
            ->andReturn($emptyArr);

        /*
         * Execute Test
         */
        $result = Mollie_WC_Plugin::paymentMethodsImages();

        self::assertEquals($emptyArr,$result );
    }
}
