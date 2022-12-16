<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class DataToPayPalButtonScriptsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     *
     */
    public function testPayPalScriptDataOnProduct()
    {
        /*
         * Stubs
         */
        $minAmount = 3;
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'product' => [
                'needShipping' => false,
                'id' => $postDummyData->productId,
                'price' => '1',
                'isVariation' => false,
                'minFee' =>$minAmount
            ],
            'ajaxUrl' => 'admin-ajax.php'
        ];
        stubs(
            [
                'wc_shipping_enabled' => true,
                'wc_get_shipping_method_count' => 1,
                'wc_get_base_location' => ['country' => 'IT'],
                'get_woocommerce_currency' => 'EUR',
                'get_bloginfo' => 'test',
                'is_product' => true,
                'get_the_id' => $postDummyData->productId,
                'admin_url' => 'admin-ajax.php',
                'get_option' => ['mollie_paypal_button_minimum_amount' => $minAmount],
                'wc_get_product' => $this->wcProduct(),
            ]
        );


        /*
         * Sut
         */
        $pluginUrl = 'http://plugingUrl.com';
        $dataToScript = new DataToPayPal($pluginUrl);

        /*
         * Execute Test
         */
        $result = $dataToScript->paypalbuttonScriptData();
        self::assertEquals($expected, $result);
    }

    public function testApplePayScriptDataOnCart()
    {
        /*
         * Stubs
         */
        $minAmount = 3;
        $subtotal = '1';
        $expected = [
            'product' => [
                'needShipping' => false,
                'minFee' =>$minAmount
            ],
            'ajaxUrl' => 'admin-ajax.php'
        ];
        stubs(
            [
                'is_product' => false,
                'is_cart' => true,
                'admin_url' => 'admin-ajax.php',
                'WC' => $this->wooCommerce($subtotal),
                'get_option'=>['mollie_paypal_button_minimum_amount'=>$minAmount],
            ]
        );

        /*
         * Sut
         */
        $pluginUrl = 'http://plugingUrl.com';
        $dataToScript = new DataToPayPal($pluginUrl);

        /*
         * Execute Test
         */
        $result = $dataToScript->paypalbuttonScriptData();
        self::assertEquals($expected, $result);
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcProduct()
    {
        return $this->createConfiguredMock(
            'WC_Product',
            [
                'get_price' => '1',
                'get_type' => 'simple',
                'needs_shipping' => false,
                'is_type' => false,
            ]
        );
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wooCommerce(
        $subtotal = 0,
        $shippingTotal = 0,
        $total = 0,
        $tax = 0
    ) {
       return $this->woocommerceMocks->wooCommerce(
            $subtotal,
            $shippingTotal,
            $total,
            $tax,
           'ES',
           false
        );
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg(1);
    }

}
