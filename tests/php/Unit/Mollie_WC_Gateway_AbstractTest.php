<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerce\Tests\Unit;

use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Tests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;
use Mollie_WC_Payment_Object;
use UnexpectedValueException;

/**
 * Class Mollie_WC_Gateway_AbstractTest
 * @package Mollie\WooCommerce\Tests\Unit
 */
class Mollie_WC_Gateway_AbstractTest extends TestCase
{
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

        /*
         * Execute Test
         */
        $testee->activePaymentObject(mt_rand(1, 10), false);
    }

    /* -----------------------------------------------------------------------
       Test wooCommerceOrderId
     ---------------------------------------------------------------------- */

    /**
     * Test WooCommerce use Property if Wc < 3.0
     */
    public function testWooCommerceOrderIdUseProperty()
    {
        /*
         * Setup Stabs
         */
        define('WC_VERSION', mt_rand(1, 2));
        $orderId = mt_rand(1, 2);
        $order = $this->getMockBuilder('\\WC_Order')->getMock();
        $order->id = $orderId;

        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                []
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Testee
         */
        $result = $testee->wooCommerceOrderId($order);

        self::assertEquals($orderId, $result);
    }

    /**
     * Test WooCommerce use Method if WC >= 3.0
     */
    public function testWooCommerceOrderIdCallMethod()
    {
        /*
         * Setup Stabs
         */
        define('WC_VERSION', mt_rand(3, 4));
        $orderId = mt_rand(1, 2);
        $order = $this
            ->getMockBuilder('\\WC_Order')
            ->disableOriginalConstructor()
            ->setMethods(['get_id'])
            ->getMock();

        /*
         * Setup Testee
         */
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                []
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Expect get_id is called
         */
        $order
            ->expects($this->once())
            ->method('get_id')
            ->willReturn($orderId);

        /*
         * Execute Testee
         */
        $result = $testee->wooCommerceOrderId($order);

        self::assertEquals($orderId, $result);
    }
}
