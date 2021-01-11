<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Helper;

use Brain\Monkey\Expectation\Exception\NotAllowedMethod;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;

class Mollie_WC_Helper_OrderLines_Test extends TestCase
{
    /* -----------------------------------------------------------------
       get_item_category Tests
       -------------------------------------------------------------- */

    /**
     * @dataProvider voucherCategoriesDataProvider
     * @param $defaultCategory
     * @param $productCategory
     * @param $localProductCategory
     * @param $simpleVariationCategory
     * @param $expectedResult
     * @test
     */
    public function getItemCategoryReturnsCorrectFallback(
        $defaultCategory,
        $productCategory,
        $localProductCategory,
        $simpleVariationCategory,
        $expectedResult
    ) {
        $product = $this->wcProduct();
        $term = new \stdClass();
        $term->term_id = 1;
        stubs(
            [
                'get_option' => $defaultCategory,
                'get_the_terms' => [$term],
                'get_term_meta' => $productCategory
            ]
        );
        /*
         * Setup Mollie_WC_Helper_Settings
         */
        $testee = $this->buildTesteeMethodMock(
            \Mollie_WC_Helper_OrderLines::class,
            [],
            []
        );

        expect('get_post_meta')
            ->twice()
            ->andReturn($localProductCategory, $simpleVariationCategory);

        /*
         * Execute test
         */
        $result = $testee->get_item_category($product);

        self::assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function voucherCategoriesDataProvider()
    {
        return [
            [['mealvoucher_category_default'=>'meal'],[],false,false, 'meal'],
            [['mealvoucher_category_default'=>'meal'],'eco',false,false, 'eco'],
            [['mealvoucher_category_default'=>'meal'],'eco',['gift'],false, 'gift'],
            [['mealvoucher_category_default'=>'meal'],'eco',['gift'],['no_category'], 'no_category']
        ];
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcProduct()
    {
        $item = $this->createConfiguredMock(
            'WC_Product',
            [
                'get_id' => 10,
            ]
        );

        return $item;
    }
}
