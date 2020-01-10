<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Gateway;

use Mollie\Api\Resources\Payment;
use Mollie\WooCommerceTests\TestCase;
use UnexpectedValueException;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;
use Mollie_WC_Gateway_Abstract as Testee;
use Mollie_WC_Payment_Object;

/**
 * Class Mollie_WC_Gateway_AbstractTest
 * @package Mollie\WooCommerce\Tests\Unit
 */
class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    /* -----------------------------------------------------------------------
        Test wooCommerceOrderId
      ---------------------------------------------------------------------- */
    protected function setUp()
    {
        parent::setUp();

        when('__')->returnArg(1);
    }


    /* -----------------------------------------------------------------------
       Test activePaymentObject
     ---------------------------------------------------------------------- */

    /**
     * Test activePaymentObject
     */
    public function testActivePaymentObjectNullUserRedirectedToCheckoutPageWithNotice()
    {
        /*
         * Setup Stubs
         */
        $paymentObject = $this
            ->getMockBuilder(Mollie_WC_Payment_Object::class)
            ->disableOriginalConstructor()
            ->setMethods(['getActiveMolliePayment'])
            ->getMock();

        $paymentResource = $this->createMock(Payment::class);

        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['paymentObject']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Expect to retrieve the payment object
         */
        $testee
            ->expects($this->once())
            ->method('paymentObject')
            ->willReturn($paymentObject);

        /*
         * Expect to get the active mollie payment and return a valid
         *  Mollie\Api\Resources\Payment instance
         */
        $paymentObject
            ->expects($this->once())
            ->method('getActiveMolliePayment')
            ->withAnyParameters()
            ->willReturn($paymentResource);

        /*
         * Execute Test
         */
        $result = $testee->activePaymentObject(mt_rand(1, 10), false);

        self::assertEquals($paymentResource, $result);
    }

    /**
     * Test activePaymentObject throw exception because `getActiveMolliePayment` return null
     */
    public function testActivePaymentObjectThrowExceptionBecauseNotPossibleToGetThePaymentObject()
    {
        /*
         * Setup Stubs
         */
        $paymentObject = $this
            ->getMockBuilder(Mollie_WC_Payment_Object::class)
            ->disableOriginalConstructor()
            ->setMethods(['getActiveMolliePayment'])
            ->getMock();

        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['paymentObject']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Expect to retrieve the payment object
         */
        $testee
            ->expects($this->once())
            ->method('paymentObject')
            ->willReturn($paymentObject);

        /*
         * Active Mollie Payment will return a null value and
         * we expect exception
         */
        $paymentObject
            ->expects($this->once())
            ->method('getActiveMolliePayment')
            ->withAnyParameters()
            ->willReturn(null);

        /*
         * And we expect an UnexpectedValueException
         */
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Active Payment Object is not a valid Payment Resource instance'
        );

        /**
         * @var Testee $testee
         */
        $testee->activePaymentObject(mt_rand(1, 10), false);
    }

    /**
     * @test
     * Test getReturnRedirectUrlForOrder handle exception because `activePaymentObject` was null
     */
    public function getReturnRedirectUrlForOrderHandlesIfActivePaymentReturnsNull()
    {
        /*
         * Setup Stubs
         */
        $order_id = 1;
        $order = $this
            ->getMockBuilder('\\WC_Order')
            ->disableOriginalConstructor()
            ->setMethods(['get_checkout_payment_url'])
            ->getMock();
        $paymentObject = $this
            ->getMockBuilder(Mollie_WC_Payment_Object::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCancelledMolliePaymentId'])
            ->getMock();

        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['paymentObject',
                    'orderNeedsPayment',
                    'activePaymentObject',
                    'get_return_url'
                ]
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * wooCommerceOrderId will return an int
         */
        expect('wooCommerceOrderId')
            ->once()
            ->withAnyArgs()
            ->andReturn($order_id);

        /*
         * debug function will be called with this line
         */
        expect('debug')
            ->once()
            ->with("Mollie_WC_Gateway_Abstract::getReturnRedirectUrlForOrder" . " $order_id: Determine what the redirect URL in WooCommerce should be.");

        /*
         * orderNeedsPayment will be true
         */
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->willReturn(true);

        /*
         * Expect to retrieve the payment object
         */
        $testee
            ->expects($this->once())
            ->method('paymentObject')
            ->willReturn($paymentObject);

        /*
         * getCancelledMolliePaymentId will return false
         */
        $paymentObject
            ->expects($this->once())
            ->method('getCancelledMolliePaymentId')
            ->withAnyParameters()
            ->willReturn(false);

        /*
         * activePaymentObject will throw an unexpectedValueException
         */
        $testee
            ->expects($this->once())
            ->method('activePaymentObject')
            ->withAnyParameters()
            ->willThrowException(new UnexpectedValueException);

        /*
         * And we expect to addNotice of it
         */

        expect('notice')
            ->once()
            ->with(__('Your payment was not successful. Please complete your order with a different payment method.', 'mollie-payments-for-woocommerce' ));

        /*
         * Finally we call $this->get_return_url( $order ) and return the url string
         */
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->withAnyParameters()
            ->willReturn('url');

        /**
         * @var Testee $testee
         */
        $testee->getReturnRedirectUrlForOrder($order);
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
        $woocommerce = $this
            ->buildTesteeMock(
                \WooCommerce::class,
                [],
                ['api_request_url']
            )
            ->getMock();
        $order = $this->buildTesteeMock(
            '\\WC_Order',
            [],
            ['get_id', 'get_order_key']
        )->getMock();

        /*
        * Expectations
        */
        expect('WC')
            ->once()
            ->andReturn($woocommerce);
        $woocommerce
            ->expects($this->once())
            ->method('api_request_url')
            ->with('mollie_return')
            ->willReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/'
            );
        $order
            ->expects($this->any())
            ->method('get_id')
            ->willReturn(89);
        $order
            ->expects($this->any())
            ->method('get_order_key')
            ->willReturn('eFZyH8jki6fge');

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
            ->once()
            ->withAnyArgs();
        /*
         * Execute test
         */

        $result = $testee->getReturnUrl($order);

        self::assertEquals(
            'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge',
            $result
        );
    }

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
        $woocommerce = $this
            ->buildTesteeMock(
                \WooCommerce::class,
                [],
                ['api_request_url']
            )
            ->getMock();
        $order = $this->buildTesteeMock(
            '\\WC_Order',
            [],
            ['get_id', 'get_order_key']
        )->getMock();
        /*
        * Expectations
        */
        expect('get_home_url')
            ->once()
            ->andReturn('http://mollie-wc.docker.myhost/');
        expect('WC')
            ->once()
            ->andReturn($woocommerce);

        $woocommerce
            ->expects($this->once())
            ->method('api_request_url')
            ->willReturn(
                'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/'
            );
        $order
            ->expects($this->any())
            ->method('get_id')
            ->willReturn(89);
        $order
            ->expects($this->any())
            ->method('get_order_key')
            ->willReturn('eFZyH8jki6fge');

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
            ->once()
            ->withAnyArgs();
        /*
         * Execute test
         */

        $result = $testee->getWebhookUrl($order);

        self::assertEquals(
            'http://mollie-wc.docker.myhost/nl/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge',
            $result
        );
    }
}

