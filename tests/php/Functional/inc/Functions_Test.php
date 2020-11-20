<?php

namespace Mollie\WooCommerceTests;

use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class Functions_Test extends TestCase
{
    /**
     * @dataProvider isCheckoutContextDataProvider
     * @param $isCheckout
     * @param $isCheckoutPage
     * @param $expected
     */
    public function testIsCheckoutContext($isCheckout, $isCheckoutPage, $expected)
    {
        stubs(
            [
                'is_checkout' => $isCheckout,
                'is_checkout_pay_page' => $isCheckoutPage,
            ]
        );

        /*
         * Execute Test
         */
        $result = mollieWooCommerceIsCheckoutContext();

        self::assertEquals($expected, $result);
    }

    public function isCheckoutContextDataProvider()
{
    return [
        [
            'is_checkout' => false,
            'is_checkout_pay_page' => false,
            'expected' => false,
        ],
        [
            'is_checkout' => true,
            'is_checkout_pay_page' => false,
            'expected' => true,
        ],
        [
            'is_checkout' => false,
            'is_checkout_pay_page' => true,
            'expected' => true,
        ],
    ];
}

    protected function setUp()
    {
        parent::setUp();
    }

    /* -----------------------------------------------------------------
       Woocommerce Functions Tests
       -------------------------------------------------------------- */
    /**
     * Test WooCommerce use Method if WC >= 3.0
     */
    public function testWooCommerceOrderIdCallMethod()
    {
        /*
         * Setup Stubs
         */

        when('mollieWooCommerceWcVersion')
            ->justReturn(number_format(mt_rand(30, 39) / 10, 1));

        $orderId = 1;
        $order = $this->createConfiguredMock(
            '\\WC_Order',
            [
                'get_id' => $orderId
            ]
        );

        /*
         * Execute Testee
         */
        $result = mollieWooCommerceOrderId($order);
        self::assertEquals($orderId, $result);
    }

    /**
     * Test WooCommerce use Property if Wc < 3.0
     */
    public function testWooCommerceOrderIdUseProperty()
    {
        /*
         * Setup Stubs
         */
        when('mollieWooCommerceWcVersion')
            ->justReturn(number_format(mt_rand(10, 29) / 10, 1));
        $orderId = mt_rand(1, 999);
        $order = $this->getMockBuilder('\\WC_Order')->getMock();
        $order->id = $orderId;

        /*
         * Execute Testee
         */
        $result = mollieWooCommerceOrderId($order);

        self::assertEquals($orderId, $result);
    }
    /**
     * Test WooCommerce use Method if WC >= 3.0
     */
    public function testWooCommerceOrderKeyCallMethod()
    {
        /*
         * Setup Stubs
         */

        when('mollieWooCommerceWcVersion')
            ->justReturn(number_format(mt_rand(30, 39) / 10, 1));

        $orderKey = 'eFZyH8jki6fge';
        $order = $this->createConfiguredMock(
            '\\WC_Order',
            [
                'get_order_key' => $orderKey
            ]
        );

        /*
         * Execute Testee
         */
        $result = mollieWooCommerceOrderKey($order);
        self::assertEquals($orderKey, $result);
    }

    /**
     * Test WooCommerce use Property if Wc < 3.0
     */
    public function testWooCommerceOrderKeyUseProperty()
    {
        /*
         * Setup Stubs
         */
        when('mollieWooCommerceWcVersion')
            ->justReturn((string)(float)(mt_rand(10, 29) / 10));
        $orderKey = 'eFZyH8jki6fge';
        $order = $this->getMockBuilder('\\WC_Order')->getMock();
        $order->order_key = $orderKey;

        /*
         * Execute Testee
         */
        $result = mollieWooCommerceOrderKey($order);

        self::assertEquals($orderKey, $result);
    }


}
