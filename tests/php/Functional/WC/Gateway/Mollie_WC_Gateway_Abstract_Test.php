<?php

namespace Mollie\WooCommerceTests\Functional\Gateway;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;


class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        when('__')->returnArg(1);
    }
    /* -----------------------------------------------------------------
  getReturnUrl Tests
  -------------------------------------------------------------- */

    /**
     * Test getReturnUrl will return correct string
     * given polylang plugin is installed.
     *
     * @test
     */
    public function getReturnUrl_ReturnsString_withPolylang()
    {
        define('WC_VERSION', '3.0');
        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['getSiteUrlWithLanguage']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $urlFromWcApi = $this
            ->buildTesteeMock(
                \WooCommerce::class,
                [],
                ['api_request_url']
            )
            ->getMock();
        $wcOrder = $this->buildTesteeMock(
            '\\WC_Order',
            [],
            ['get_id', 'get_order_key']
        )->getMock();

        /*
        * Expectations
        */
        expect('WC')
            ->andReturn($urlFromWcApi);
        $urlFromWcApi
            ->method('api_request_url')
            ->with('mollie_return')
            ->willReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/'
            );
        expect('wooCommerceOrderId')
            ->andReturn(89);
        expect('wooCommerceOrderKey')
            ->andReturn('eFZyH8jki6fge');
        expect('add_query_arg')
            ->once()
            ->with(
                array(
                    'order_id' => 89,
                    'key' => 'eFZyH8jki6fge',
                ),
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/'
            )
            ->andReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge'
            );
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn('http://mollie-wc.docker.myhost/nl/');
        expect('debug')
            ->withAnyArgs();
        /*
         * Execute test
         */

        $result = $testee->getReturnUrl($wcOrder);

        self::assertEquals(
            'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge',
            $result
        );
    }
    /* -----------------------------------------------------------------
      getWebhookUrl Tests
      -------------------------------------------------------------- */
    /**
     * Test getWebhookUrl will return correct string
     * given polylang plugin is installed.
     *
     * @test
     */
    public function getWebhookUrl_ReturnsString_withPolylang()
    {
        define('WC_VERSION', '3.0');
        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['getSiteUrlWithLanguage']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $urlFromWcApi = $this
            ->buildTesteeMock(
                \WooCommerce::class,
                [],
                ['api_request_url']
            )
            ->getMock();
        $wcOrder = $this->buildTesteeMock(
            '\\WC_Order',
            [],
            ['get_id', 'get_order_key']
        )->getMock();
        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn('http://mollie-wc.docker.myhost/');
        expect('WC')
            ->andReturn($urlFromWcApi);
        $urlFromWcApi
            ->method('api_request_url')
            ->willReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/'
            );
        expect('wooCommerceOrderId')
            ->andReturn(89);
        expect('wooCommerceOrderKey')
            ->andReturn('eFZyH8jki6fge');
        expect('add_query_arg')
            ->once()
            ->with(
                array(
                    'order_id' => 89,
                    'key' => 'eFZyH8jki6fge',
                ),
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/'
            )
            ->andReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge'
            );
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn('http://mollie-wc.docker.myhost/nl/');
        expect('debug')
            ->withAnyArgs();
        /*
         * Execute test
         */

        $result = $testee->getWebhookUrl($wcOrder);

        self::assertEquals(
            'http://mollie-wc.docker.myhost/nl/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge',
            $result
        );
    }
}
