<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\WC\Helper;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Helper_MaybeDisableGateway;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Plugin_Test
 */
class Mollie_WC_Helper_MaybeDisableGateway_Test extends TestCase
{
    public function testNumberProductsWithNoCategory()
    {
        /*
         * Stubs
         */

        $expected = 0;
        stubs(
            [
                'get_option' => ['mealvoucher_category_default' => ''],
                'get_post_meta' => ['_mollie_voucher_category'=>[0=>'no_category']],
                'WC' => $this->wooCommerce()

            ]
        );


        /*
         * Sut
         */
        $applePayDirectHandler = new Mollie_WC_Helper_MaybeDisableGateway();

        /*
         * Execute Test
         */
        $result = $applePayDirectHandler->numberProductsWithCategory();

        self::assertEquals($expected, $result);
    }
    public function testNumberProductsWithAllCategory()
    {
        /*
         * Stubs
         */

        $expected = 2;
        stubs(
            [
                'get_option' => ['mealvoucher_category_default' => 'food_and_drinks'],
                'get_post_meta' => ['_mollie_voucher_category'=>[0=>'food_and_drinks']],
                'WC' => $this->wooCommerce()

            ]
        );


        /*
         * Sut
         */
        $applePayDirectHandler = new Mollie_WC_Helper_MaybeDisableGateway();

        /*
         * Execute Test
         */
        $result = $applePayDirectHandler->numberProductsWithCategory();

        self::assertEquals($expected, $result);
    }

    public function testNumberProductsWithMissingCategory()
    {
        /*
         * Stubs
         */

        $expected = 1;
        stubs(
            [
                'get_option' => ['mealvoucher_category_default' => 'food_and_drinks'],
                'WC' => $this->wooCommerce()

            ]
        );
        expect('get_post_meta')
            ->times(2)
            ->withAnyArgs()
            ->andReturn(
                            ['_mollie_voucher_category'=>[0=>'food_and_drinks']],
                            ['_mollie_voucher_category'=>[0=>'no_category']]
                        );


        /*
         * Sut
         */
        $applePayDirectHandler = new Mollie_WC_Helper_MaybeDisableGateway();

        /*
         * Execute Test
         */
        $result = $applePayDirectHandler->numberProductsWithCategory();

        self::assertEquals($expected, $result);
    }


    protected function setUp()
    {
        parent::setUp();

        $this->fileMTime = time();

        when('filemtime')->justReturn($this->fileMTime);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wooCommerce(

    ) {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [
            ]
        );
        $item->cart = $this->wcCart();


        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCart()
    {
        $item = $this->createConfiguredMock(
            'WC_Cart',
            [
                'get_cart_contents' => [['product_id'=>1], ['product_id'=>2]]
            ]
        );

        return $item;
    }
}
