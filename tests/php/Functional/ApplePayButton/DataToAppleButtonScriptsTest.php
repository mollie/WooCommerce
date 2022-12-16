<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Buttons\ApplePayButton\DataToAppleButtonScripts;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class ApplePayDirectHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     *
     */
    public function testApplePayScriptDataOnProduct()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'product' => [
                'needShipping' => $postDummyData->needShipping,
                'id' => $postDummyData->productId,
                'price' => '1',
                'isVariation' => false,
            ],
            'shop' => [
                'countryCode' => 'IT',
                'currencyCode' => 'EUR',
                'totalLabel' => 'test'
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
                'wc_get_product' => $this->wcProduct(),
                'admin_url' => 'admin-ajax.php',
                'get_option' => false,
            ]
        );


        /*
         * Sut
         */
        $dataToScript = new DataToAppleButtonScripts();

        /*
         * Execute Test
         */
        $result = $dataToScript->applePayScriptData();
        self::assertEquals($expected, $result);
    }

    public function testApplePayScriptDataOnCart()
    {
        /*
         * Stubs
         */
        $subtotal = '1';
        $expected = [
            'product' => [
                'needShipping' => true,
                'subtotal' => $subtotal,
            ],
            'shop' => [
                'countryCode' => 'IT',
                'currencyCode' => 'EUR',
                'totalLabel' => 'test',
            ],
            'ajaxUrl' => 'admin-ajax.php',
            'buttonMarkup' => '<div id="mollie-applepayDirect-button">testNonce</div>',
        ];
        stubs(
            [
                'wc_get_base_location' => ['country' => 'IT'],
                'get_woocommerce_currency' => 'EUR',
                'get_bloginfo' => 'test',
                'is_product' => false,
                'is_cart' => true,
                'admin_url' => 'admin-ajax.php',
                'WC' => $this->wooCommerce($subtotal),
                'wp_nonce_field'=> 'testNonce',
            ]
        );


        /*
         * Sut
         */
        $dataToScript = new DataToAppleButtonScripts();

        /*
         * Execute Test
         */
        $result = $dataToScript->applePayScriptData();
        self::assertEquals($expected, $result);
    }

    private function wcProduct()
    {
       return $this->woocommerceMocks->wcProduct();
    }

    private function wooCommerce(
        $subtotal = 0,
        $shippingTotal = 0,
        $total = 0,
        $tax = 0
    ) {
        return $this->woocommerceMocks->wooCommerce($subtotal, $shippingTotal, $total, $tax);
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
