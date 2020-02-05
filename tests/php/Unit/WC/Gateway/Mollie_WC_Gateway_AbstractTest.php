<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Gateway;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;
use function Brain\Monkey\Functions\expect;

/**
 * Class Mollie_WC_Helper_Settings_Test
 */
class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    /* -----------------------------------------------------------------
       getIconUrl Tests
       -------------------------------------------------------------- */

    /**
     * Test getIconUrl will return the url string
     *
     * @test
     */
    public function getIconUrlReturnsUrlString()
    {
        /*
         * Setup Stubs to mock the API call
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
        //this part is the same code as data::getApiPaymentMethods
        $methods[] = $methodIdeal;
        $methods_cleaned = array();
        foreach ( $methods as $method ) {
            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        /*
        * Expect to call availablePaymentMethods() function and return a mock of one method with id 'ideal'
        */
        expect('availablePaymentMethods')
            ->once()
            ->withNoArgs()
            ->andReturn($methods);

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();

        self::assertEquals('https://mollie.com/external/icons/payment-methods/ideal.svg', $result);

    }
    /**
     * Test getIconUrl will return the url string even if Api returns empty
     *
     * @test
     */
    public function getIconUrlReturnsUrlStringFromAssets()
    {

        /*
        * Expect to call availablePaymentMethods() function and return false from the API
        */
        expect('availablePaymentMethods')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();

        self::assertStringEndsWith('assets/images/ideal.svg', $result);

    }

    /**
     * Test associativePaymentMethodsImages returns associative array
     * ordered by id (method name) of image urls
     *
     * @test
     */
    public function associativePaymentMethodsImagesReturnsArrayOrderedById()
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
        $paymentMethodsImagesResult = [
            "ideal" => $methodIdeal->image
        ];
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Test
         */
        $result = $testee->associativePaymentMethodsImages($methods);

        self::assertEquals($paymentMethodsImagesResult,$result );
    }

    /**
     * Test associativePaymentMethodsImages returns array ordered by id of payment method to access images directly
     *
     * @test
     */
    public function associativePaymentMethodsImagesReturnsEmptyArrayIfApiFails()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Test
         */
        $emptyArr = [];
        $result = $testee->associativePaymentMethodsImages($emptyArr);

        self::assertEquals($emptyArr, $result);
    }

    /* -----------------------------------------------------------------
     getReturnUrl Tests
     -------------------------------------------------------------- */

    /**
     * Test getReturnUrl
     * given polylang plugin is installed
     * then will return correct string
     *
     * @test
     */
    public function getReturnUrl_ReturnsString_withPolylang()
    {
        /*
         * Setup Testee
         */
        $testee = $this->testPolylangTestee();

        //set variables
        list(
            $orderId, $orderKey, $homeUrl, $apiRequestUrl, $untrailedUrl,
            $urlWithParams,
            $untrailedWithParams
            )
            = $this->testPolylangVariables();

        /*
        * Setup Stubs
        */
        $wcUrl = $this->createConfiguredMock(
            \WooCommerce::class,
            ['api_request_url' => $apiRequestUrl]
        );
        $wcOrder = $this->createMock('WC_Order');


        /*
        * Expectations
        */
        //get url from request
        expect('WC')
            ->andReturn($wcUrl);
        //delete url final slash
        expect('untrailingslashit')
            ->twice()
            ->andReturn($untrailedUrl, $untrailedWithParams);
        //get order id and key and append to the the url
        expect('wooCommerceOrderId')
            ->andReturn($orderId);
        expect('wooCommerceOrderKey')
            ->andReturn($orderKey);
        $testee
            ->expects($this->once())
            ->method('appendOrderArgumentsToUrl')
            ->with($orderId, $orderKey, $untrailedUrl)
            ->willReturn($urlWithParams);

        //check for multilanguage plugin enabled and receive url
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn("{$apiRequestUrl}/nl");

        expect('debug')
            ->withAnyArgs();

        /*
         * Execute test
         */
        $result = $testee->getReturnUrl($wcOrder);

        self::assertEquals($urlWithParams, $result);
    }

    /* -----------------------------------------------------------------
      getWebhookUrl Tests
      -------------------------------------------------------------- */
    /**
     * Test getWebhookUrl
     * given polylang plugin is installed
     * then will return correct string
     *
     * @test
     */
    public function getWebhookUrl_ReturnsString_withPolylang()
    {
        /*
         * Setup Testee
         */
        $testee = $this->testPolylangTestee();

        //set variables
        list(
            $orderId, $orderKey, $homeUrl, $apiRequestUrl, $untrailedUrl,
            $urlWithParams,
            $untrailedWithParams
            )
            = $this->testPolylangVariables();

        /*
        * Setup Stubs
        */
        $wcUrl = $this->createConfiguredMock(
            \WooCommerce::class,
            ['api_request_url' => $apiRequestUrl]
        );
        $wcOrder = $this->createMock('WC_Order');


        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn($homeUrl);
        //get url from request
        expect('WC')
            ->andReturn($wcUrl);
        //delete url final slash
        expect('untrailingslashit')
            ->twice()
            ->andReturn($untrailedUrl, $untrailedWithParams);
        //get order id and key and append to the the url
        expect('wooCommerceOrderId')
            ->andReturn($orderId);
        expect('wooCommerceOrderKey')
            ->andReturn($orderKey);
        $testee
            ->expects($this->once())
            ->method('appendOrderArgumentsToUrl')
            ->with($orderId, $orderKey, $untrailedUrl)
            ->willReturn($urlWithParams);
        //check for multilanguage plugin enabled, receives url and adds it
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn("{$homeUrl}/nl");
        expect('debug')
            ->withAnyArgs();

        /*
         * Execute test
         */
        $result = $testee->getWebhookUrl($wcOrder);

        self::assertEquals(
            "{$homeUrl}/nl/wc-api/mollie_return/?order_id={$orderId}&key=wc_order_{$orderKey}",
            $result
        );
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function testPolylangTestee()
    {
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['getSiteUrlWithLanguage', 'appendOrderArgumentsToUrl']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        return $testee;
    }

    /**
     * @return array
     */
    protected function testPolylangVariables()
    {
        $orderId = $this->faker->randomDigit;
        $orderKey = $this->faker->word;
        $homeUrl = rtrim($this->faker->url, '/\\');
        $apiRequestUrl = "{$homeUrl}/wc-api/mollie_return";
        $untrailedUrl = rtrim($apiRequestUrl, '/\\');
        $urlWithParams
            = "{$untrailedUrl}/?order_id={$orderId}&key=wc_order_{$orderKey}";
        $untrailedWithParams = rtrim($urlWithParams, '/\\');
        return array(
            $orderId,
            $orderKey,
            $homeUrl,
            $apiRequestUrl,
            $untrailedUrl,
            $urlWithParams,
            $untrailedWithParams
        );
    }
}
