<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\WalletEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_ApplePayButton_DataToAppleButtonScripts;
use Mollie_WC_Helper_Api;
use Mollie_WC_ApplePayButton_DataObjectHttp;
use Mollie_WC_Helper_ApplePayDirectHandler;
use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_RefundLineItemsBuilder;
use Mollie_WC_PayPalButton_DataToPayPalScripts;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use WC_Countries;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class DataToPayPalButtonScriptsTest extends TestCase
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
                'wc_get_product' => $this->wcProduct(),
                'admin_url' => 'admin-ajax.php',
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
        $item = $this->createConfiguredMock(
            'WooCommerce',
            []
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
        return $this->createConfiguredMock(
            'WC_Cart',
            [
                'needs_shipping' => false,
                'get_subtotal' => $subtotal,
                'is_empty' => true,
                'get_shipping_total' => $shippingTotal,
                'add_to_cart' => '88888',
                'get_total_tax' => $tax,
                'get_total' => $total,
                'calculate_shipping' => null,
            ]
        );
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
                'get_cost' => $cost,
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
    protected function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg(1);
    }

}
