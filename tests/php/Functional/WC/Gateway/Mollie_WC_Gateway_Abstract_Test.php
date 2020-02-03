<?php

namespace Mollie\WooCommerceTests\Functional\Gateway;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use Faker;


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
        $testee = $this->buildTesteeMock(
                Testee::class,
                [],
                ['getSiteUrlWithLanguage']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Setup Stubs
         */
        $urlFromWcApi = $this->createConfiguredMock(
                \WooCommerce::class,
                ['api_request_url'=>'http://mollie-wc.docker.myhost/wc-api/mollie_return/']
            );
        $wcOrder = $this->createMock('WC_Order');
        $faker = Faker\Factory::create();
        $id = $faker->randomDigit;
        $key = $faker->word;
        /*
        * Expectations
        */
        expect('WC')
            ->andReturn($urlFromWcApi);
        expect('wooCommerceOrderId')
            ->andReturn($id);
        expect('wooCommerceOrderKey')
            ->andReturn($key);
        expect('add_query_arg')
            ->once()
            ->with(
                array(
                    'order_id' => $id,
                    'key' => $key,
                ),
                'http://mollie-wc.docker.myhost/wc-api/mollie_return'
            )
            ->andReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id='.$id.'&key=wc_order_'.$key
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
            'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id='.$id.'&key=wc_order_'.$key,
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
        /*
        * Setup Stubs
        */
        $urlFromWcApi = $this->createConfiguredMock(
            \WooCommerce::class,
            ['api_request_url'=>'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/']
        );
        $wcOrder = $this->createMock('WC_Order');
        $faker = Faker\Factory::create();
        $id = $faker->randomDigit;
        $key = $faker->word;
        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn('http://mollie-wc.docker.myhost/');
        expect('WC')
            ->andReturn($urlFromWcApi);
        expect('wooCommerceOrderId')
            ->andReturn($id);
        expect('wooCommerceOrderKey')
            ->andReturn($key);
        expect('add_query_arg')
            ->once()
            ->with(
                array(
                    'order_id' => $id,
                    'key' => $key,
                ),
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact'
            )
            ->andReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id='.$id.'&key=wc_order_'.$key
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
            'http://mollie-wc.docker.myhost/nl/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id='.$id.'&key=wc_order_'.$key,
            $result
        );
    }
}
