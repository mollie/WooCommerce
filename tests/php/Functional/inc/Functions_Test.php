<?php

namespace Mollie\WooCommerceTests;

use function Brain\Monkey\Functions\stubs;

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
        $result = isCheckoutContext();

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

        require_once PROJECT_DIR . '/inc/functions.php';
    }
}
