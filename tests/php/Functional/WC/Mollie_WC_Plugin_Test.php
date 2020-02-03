<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\WC;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Plugin;
use Mollie_WC_Helper_Data;
use WC_Order;
use WpScriptsStub;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;
use Faker;

/**
 * Class Mollie_WC_Plugin_Test
 */
class Mollie_WC_Plugin_Test extends TestCase
{
    private $fileMTime;

    public function testGetPluginUrl()
    {
        /*
         * Stubs
         */
        $path = uniqid();
        when('untrailingslashit')->returnArg(1);

        /*
         * Execute test
         */
        self::assertEquals(
            M4W_PLUGIN_URL . '/',
            Mollie_WC_Plugin::getPluginPath()
        );
        self::assertEquals(
            M4W_PLUGIN_URL . "/{$path}",
            Mollie_WC_Plugin::getPluginPath("/{$path}")
        );
        self::assertEquals(
            M4W_PLUGIN_URL . '/',
            Mollie_WC_Plugin::getPluginPath('/')
        );
    }

    public function testGetPluginDir()
    {
        /*
         * Stubs
         */
        $path = uniqid();
        when('untrailingslashit')->returnArg(1);

        /*
         * Execute test
         */
        self::assertEquals(
            M4W_PLUGIN_DIR . '/',
            Mollie_WC_Plugin::getPluginPath()
        );
        self::assertEquals(
            M4W_PLUGIN_DIR . "/{$path}",
            Mollie_WC_Plugin::getPluginPath("/{$path}")
        );
        self::assertEquals(
            M4W_PLUGIN_DIR . '/',
            Mollie_WC_Plugin::getPluginPath('/')
        );
    }

    public function testRegisterFrontendScriptsRegisterPolyfill()
    {
        /*
         * Execute Test
         */
        Mollie_WC_Plugin::registerFrontendScripts();

        $wpScriptsStub = WpScriptsStub::instance();

        /*
         * Polyfill
         */
        $babelPolifyll = $wpScriptsStub->registered('script', 'babel-polyfill');
        self::assertEquals('babel-polyfill', $babelPolifyll[0]);
        self::assertEquals(M4W_PLUGIN_URL . '/public/js/babel-polyfill.min.js', $babelPolifyll[1]);
        self::assertEquals($this->fileMTime, $babelPolifyll[3]);
        self::assertEquals(true, $babelPolifyll[4]);
    }

    public function testRegisterFrontendScriptsRegisterApplePay()
    {
        /*
         * Execute Test
         */
        Mollie_WC_Plugin::registerFrontendScripts();

        $wpScriptsStub = WpScriptsStub::instance();

        /*
         * Apple Pay
         */
        $applepayScript = $wpScriptsStub->registered('script', 'mollie_wc_gateway_applepay');
        self::assertEquals('mollie_wc_gateway_applepay', $applepayScript[0]);
        self::assertEquals(M4W_PLUGIN_URL . '/public/js/applepay.min.js', $applepayScript[1]);
        self::assertEquals([], $applepayScript[2]);
        self::assertEquals($this->fileMTime, $applepayScript[3]);
        self::assertEquals(true, $applepayScript[4]);
    }

    public function testRegisterFrontendScriptsRegisterMollieComponents()
    {
        /*
         * Execute Test
         */
        Mollie_WC_Plugin::registerFrontendScripts();

        $wpScriptsStub = WpScriptsStub::instance();

        /*
        * Mollie JS
        */
        $mollieScript = $wpScriptsStub->registered('script', 'mollie');
        self::assertEquals('mollie', $mollieScript[0]);
        self::assertEquals('https://js.mollie.com/v1/mollie.js', $mollieScript[1]);
        self::assertEquals([], $mollieScript[2]);
        self::assertEquals(null, $mollieScript[3]);
        self::assertEquals(true, $mollieScript[4]);

        /*
         * Mollie Components Css
         */
        $mollieComponentsStyle = $wpScriptsStub->registered('style', 'mollie-components');
        self::assertEquals('mollie-components', $mollieComponentsStyle[0]);
        self::assertEquals(
            M4W_PLUGIN_URL . '/public/css/mollie-components.min.css',
            $mollieComponentsStyle[1]
        );
        self::assertEquals([], $mollieComponentsStyle[2]);
        self::assertEquals($this->fileMTime, $mollieComponentsStyle[3]);
        self::assertEquals('screen', $mollieComponentsStyle[4]);

        /*
         * Mollie Components Js
         */
        $mollieComponentsScript = $wpScriptsStub->registered('script', 'mollie-components');
        self::assertEquals('mollie-components', $mollieComponentsScript[0]);
        self::assertEquals(
            M4W_PLUGIN_URL . '/public/js/mollie-components.min.js',
            $mollieComponentsScript[1]
        );
        self::assertEquals(
            ['underscore', 'jquery', 'mollie', 'babel-polyfill'],
            $mollieComponentsScript[2]
        );
        self::assertEquals($this->fileMTime, $mollieComponentsScript[3]);
        self::assertEquals(true, $mollieComponentsScript[4]);
    }

    public function testEnqueueFrontendScriptsInCheckoutPage()
    {
        /*
         * Stubs
         */
        stubs(
            [
                'is_admin' => false,
                'isCheckoutContext' => true,
            ]
        );

        /*
         * Execute Test
         */
        Mollie_WC_Plugin::enqueueFrontendScripts();

        $wpScriptsStub = WpScriptsStub::instance();

        self::assertEquals(
            true,
            $wpScriptsStub->isEnqueued('script', 'mollie_wc_gateway_applepay')
        );
    }

    /**
     * @dataProvider frontendCheckoutContextDataProvider
     * @param $isAdmin
     * @param $isCheckoutContext
     */
    public function testEnqueueFrontendScriptsDoesNotEnqueueInInvalidContext(
        $isAdmin,
        $isCheckoutContext
    ) {

        /*
         * Stubs
         */
        stubs(
            [
                'is_admin' => $isAdmin,
                'isCheckoutContext' => $isCheckoutContext,
            ]
        );

        /*
         * Execute Test
         */
        Mollie_WC_Plugin::enqueueFrontendScripts();

        self::assertEquals(true, empty(WpScriptsStub::instance()->allEnqueued('script')));
    }

    public function testEnqueueComponentsAssets()
    {
        stubs(
            [
                'merchantProfileId' => uniqid(),
                'mollieComponentsStylesForAvailableGateways' => [uniqid()],
                'is_admin' => false,
                'isCheckoutContext' => true,
                'get_locale' => uniqid(),
                'isTestModeEnabled' => true,
                'esc_html__' => '',
                'is_checkout' => true,
                'is_checkout_pay_page' => false,
            ]
        );

        /*
         * Expect to localize script for mollie components
         */
        expect('wp_localize_script')
            ->once()
            ->andReturnUsing(
                function ($handle, $objectName, $data) {
                    self::assertEquals('mollie-components', $handle);
                    self::assertEquals('mollieComponentsSettings', $objectName);
                    self::assertEquals('array', gettype($data));
                }
            );

        /*
         * Execute Test
         */
        Mollie_WC_Plugin::enqueueComponentsAssets();

        $wpScriptsStub = WpScriptsStub::instance();

        self::assertEquals(true, $wpScriptsStub->isEnqueued('style', 'mollie-components'));
        self::assertEquals(true, $wpScriptsStub->isEnqueued('script', 'mollie-components'));
    }

    public function frontendCheckoutContextDataProvider()
    {
        // Admin, CheckoutPage
        return [
            [false, false],
            [true, false],
        ];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->fileMTime = time();

        when('filemtime')->justReturn($this->fileMTime);
    }
    /* -----------------------------------------------------------------
       onMollieReturn Tests
       -------------------------------------------------------------- */

    /**
     * Given onMollieReturn is called
     * when order and gateway are valid
     * then we get a redirectUrl
     *
     * @test
     */
    public function onMollieReturn_redirectUrlCorrect()
    {
        $faker = Faker\Factory::create();
        $id = $faker->uuid;
        $key = $faker->word;
        $gwId = $faker->word;
        $redirectUrl
            = "https://website.org/language/order-received/{$id}/?key=wc_order_{$key}";
        /*
         * Setup Stubs to mock the external dependencies and its methods
         */
        $gateway = $this->getMockBuilder(Mollie_WC_Gateway_Abstract::class)
            ->disableOriginalConstructor()
            ->setMethods(['getReturnRedirectUrlForOrder'])
            ->setMockClassName('Mollie_WC_Gateway_Abstract')
            ->getMock();
        $gateway->id = $gwId;
        $order = $this->createConfiguredMock(
            WC_Order::class,
            ['key_is_valid' => true]
        );
        $dataHelper = $this->createConfiguredMock(
            Mollie_WC_Helper_Data::class,
            [
                'getWcOrder' => $order,
                'getWcPaymentGatewayByOrder' => $gateway
            ]
        );

        /*
        * Expectations
        */
        expect('getDataHelper')
            ->andReturn($dataHelper);
        when('wc_get_order_id_by_order_key')
            ->justReturn($id);
        when('wooCommerceOrderId')
            ->justReturn($id);
        $gateway
            ->expects($this->once())
            ->method('getReturnRedirectUrlForOrder')
            ->willReturn($redirectUrl);
        when('add_query_arg')
            ->justReturn(
                "https://website.org/language/order-received/{$id}/?key=wc_order_{$key}&utm_nooverride=1"
            );
        expect('wp_safe_redirect')
            ->once()
            ->andReturn(true);
        when('debug')
            ->justReturn(true);

        /*
         * Execute Test
         */
        Mollie_WC_Plugin::onMollieReturn();
    }
}
