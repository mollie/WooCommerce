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
                'admin_url' => 'admin-ajax.php'
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
                'get_price' => '1',
                'get_type' => 'simple',
                'needs_shipping' => true,
            ]
        );

        return $item;
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
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [

            ]
        );
        $item->cart = $this->wcCart($subtotal, $shippingTotal, $total, $tax);
        $item->customer = $this->wcCustomer();
        $item->shipping = $this->wcShipping();
        $item->session = $this->wcSession();

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCart($subtotal, $shippingTotal, $total, $tax)
    {
        $item = $this->createConfiguredMock(
            'WC_Cart',
            [
                'needs_shipping' => true,
                'get_subtotal' => $subtotal,
                'is_empty' => true,
                'get_shipping_total' => $shippingTotal,
                'add_to_cart' => '88888',
                'get_total_tax' => $tax,
                'get_total' => $total,
                'calculate_shipping' => null,
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCustomer()
    {
        return $this->createConfiguredMock(
            'WC_Customer',
            [
                'get_shipping_country' => 'IT',
            ]
        );
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcShipping()
    {
        return $this->createConfiguredMock(
            'WC_Shipping',
            [
                'calculate_shipping' => [
                    0 => [
                        'rates' => [
                            $this->wcShippingRate(
                                'flat_rate:1',
                                'Flat1',
                                '1.00'
                            ),
                            $this->wcShippingRate(
                                'flat_rate:4',
                                'Flat4',
                                '4.00'
                            )
                        ]
                    ]
                ]
            ]
        );

    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcShippingRate($id, $label, $cost)
    {
        return $this->createConfiguredMock(
            'WC_Shipping_Rate',
            [
                'get_id' => $id,
                'get_label' => $label,
                'get_cost' => $cost

            ]
        );
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcSession()
    {
        return $this->createConfiguredMock(
            'WC_Session',
            [
                'set' => null,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        when('__')->returnArg(1);
    }

}
