<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Payment;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Payment_Order;

/**
 * Class Mollie_WC_Payment_Order_Test
 */
class Mollie_WC_Payment_Order_Test extends TestCase
{
    /**
     * Test maximalFieldLength returns same string if <= maximalLength
     *
     * @test
     */
    public function maximalFieldLengthNoChange()
    {
        /*
         * Setup stubs
         */
        $field = 'no change';
        $maximalLength = 9;
        $expectedResult = 'no change';

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Mollie_WC_Payment_Order::class,
            [],
            []
        );

        /*
         * Execute Test
         */
        $result = $testee->maximalFieldLengths($field, $maximalLength);
        self::assertEquals($expectedResult, $result);
    }

    /**
     * Test maximalFieldLength returns shortened string if > maximalLength
     *
     * @test
     */
    public function maximalFieldLengthShortened()
    {
        /*
         * Setup stubs
         */
        $field = 'This will be shown not this';
        $expectedResult = 'This will be shown';
        $maximalLength = 18;


        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Mollie_WC_Payment_Order::class,
            [],
            []
        );

        /*
         * Execute Test
         */
        $result = $testee->maximalFieldLengths($field, $maximalLength);
        self::assertEquals($expectedResult, $result);
        self::assertTrue(strlen($result) === $maximalLength);
    }
}
