<?php # -*- coding: utf-8 -*-

use function Brain\Monkey\Functions\expect;
use MollieTests\TestCase;
use Mollie_WC_Helper_PaymentMethodIconUrl as Testee;

/**
 * Class PaymentMethodIconUrl
 */
class Payment_Method_Icon_Url_Test extends TestCase
{
    /**
     * Test PaymentMethodIconUrl returns svg url string when svgUrlForPaymentMethod is called
     *
     * @test
     */
    public function svgUrlForPaymentMethodReturnsSVGString()
    {
        /*
         * Setup stubs
         */
        $paymentMethodsList = [
            "ideal" => [
                "id" => "ideal",
                "images" => [
                    "size1x" => "https://mollie.com/external/icons/payment-methods/ideal.png",
                    "size2x" => "https://mollie.com/external/icons/payment-methods/ideal%402x.png",
                    "svg" => "https://mollie.com/external/icons/payment-methods/ideal.svg",
                ]
            ]
        ];

        /*
         * Setup Testee
         */
        $testee = Testee::getInstance($paymentMethodsList);

        /*
         * Execute Test
         */
        $result = $testee->svgUrlForPaymentMethod('ideal');
        self::assertEquals('https://mollie.com/external/icons/payment-methods/ideal.svg',$result );
    }

    /**
     * Test PaymentMethodIconUrl returns svg url string when svgUrlForPaymentMethod is called
     *
     * @test
     */
    public function svgUrlForPaymentMethodFallBackToAssets()
    {
        /*
         * Setup stubs
         */
        $paymentMethod = 'creditcard';
        $imageUrlexpected = 'plugin/assets/images/' . $paymentMethod . 's.svg';
        define( 'M4W_PLUGIN_URL', 'plugin/' );
        $paymentMethodsList = [];

        /*
         * Setup Testee
         */
        $testee = Testee::getInstance($paymentMethodsList);

        /*
         * Expect to call is_admin() function and return false
         */
        expect('is_admin')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        /*
         * Execute Test
         */
        $result = $testee->svgUrlForPaymentMethod($paymentMethod);
        self::assertEquals($imageUrlexpected,$result );
    }
}
