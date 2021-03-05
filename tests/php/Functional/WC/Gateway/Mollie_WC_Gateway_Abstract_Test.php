<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\WC\Gateway;

use Faker;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Ideal;
use Mollie_WC_Plugin;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;

use function Brain\Monkey\Actions\expectDone as expectedActionDone;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Plugin_Test
 */
class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    /**
     * GIVEN I RECEIVE A WC ORDER (DIFFERENT KIND OF WC PRODUCTS OR SUBSCRIPTIONS)
     * WHEN I PAY WITH ANY GATEWAY (STATUS PAID, AUTHORIZED)
     * THEN CREATES CORRECT MOLLIE REQUEST ORDER (THE EXIT TO THE API IS TESTED)
     * THEN THE DEBUG LOGS ARE CORRECT
     * THEN THE ORDER NOTES ARE CREATED
     * THEN THE RESPONSE FROM MOLLIE IS PROCESSED (STATUS COMPLETED) (THE RESPONSE FROM API IS MOCKED)
     * THEN THE STATUS OF THE WC ORDER IS AS EXPECTED (CHECK THE DB OR TEST THE EXIT)
     * THEN THE REDIRECTION FOR THE USER IS TO THE CORRECT PAGE (PAY AGAIN OR ORDER COMPLETED) SO TEST THE ULR (POLYLANG…)
     *
     * @test
     */
    public function processPayment_Order_success(){
        if ( ! defined( 'ABSPATH' ) )
            define( 'ABSPATH', dirname( __FILE__ ) . '/' );
        //build mock gateway
        /*
        * Sut
        */
        $gatewayId = 'mollie_wc_gateway_ideal';
        $issuer_id = Mollie_WC_Plugin::PLUGIN_ID . '_issuer_'. $gatewayId;
        $_POST[$issuer_id]= 'ideal_INGBNL2A';
        stubs(
            [
                'wc_get_order_status_name' => 'wc-on-hold',
                'admin_url' => 'admin.php?page=wc-settings&tab=products&section=inventory',
            ]
        );
        expect('get_option')
            ->andReturn(false);

        $testee = new Mollie_WC_Gateway_Ideal();
        /*
         * Stubs
         */
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
        $wcOrderId = 1;
        $wcOrderKey = 'wc_order_hxZniP1zDcnM8';
        $mollieOrderId = 'wvndyu';//ord_wvndyu
        $processPaymentRedirect = 'https://www.mollie.com/payscreen/order/checkout/'. $mollieOrderId;
        $expectedResult = array (
            'result'   => 'success',
            'redirect' => $processPaymentRedirect,
        );
        $wcOrder = $this->wcOrder($wcOrderId, $wcOrderKey);
        stubs(
            [
                'wc_get_order' => $wcOrder,
                'get_locale' => 'en_US',
                'wc_get_payment_gateway_by_order' => $testee,
                'get_home_url' => 'https://webshop.example.org',
                'WC' => $this->wooCommerce(),
                'add_query_arg' => 'https://webshop.example.org/wc-api/mollie_return?order_id='.$wcOrderId.'&key='.$wcOrderKey,
                'is_plugin_active' => false
            ]
        );


        /*
         *  Expectations
         */
        when('mollieWooCommerceDebug')
            ->justReturn('');

        expect('get_post_meta')
            ->andReturn(false);
        expect('wc_get_product')
            ->andReturn($this->wcProduct());
        expectedActionDone(Mollie_WC_Plugin::PLUGIN_ID . '_create_payment')
            ->once()
            ->with($this->expectedRequestData(), $wcOrder);
        /*
        * Execute Test
        */
        $arrayResult = $testee->process_payment($wcOrderId);
        self::assertEquals($expectedResult, $arrayResult);
    }

    protected function setUp()
    {
        $_POST = [];
        parent::setUp();

        when('__')->returnArg(1);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrder($id, $orderKey)
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => $id,
                'get_order_key'=>$orderKey,
                'get_total'=>'20',
                'get_items'=> [$this->wcOrderItem()],
                'get_billing_first_name'=>'billingggivenName',
                'get_billing_last_name'=>'billingfamilyName',
                'get_billing_email'=>'billingemail',
                'get_shipping_first_name'=>'shippinggivenName',
                'get_shipping_last_name'=>'shippingfamilyName',
                'get_billing_address_1'=>'shippingstreetAndNumber',
                'get_billing_address_2'=>'billingstreetAdditional',
                'get_billing_postcode'=>'billingpostalCode',
                'get_billing_city'=>'billingcity',
                'get_billing_state'=>'billingregion',
                'get_billing_country'=>'billingcountry',
                'get_shipping_address_1'=>'shippingstreetAndNumber',
                'get_shipping_address_2'=>'shippingstreetAdditional',
                'get_shipping_postcode'=>'shippingpostalCode',
                'get_shipping_city'=>'shippingcity',
                'get_shipping_state'=>'shippingregion',
                'get_shipping_country'=>'shippingcountry',
                'get_shipping_methods'=>false,
                'get_order_number'=>1,
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

    ) {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [
                'api_request_url' => 'https://webshop.example.org/wc-api/mollie_return'
            ]
        );

        return $item;
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
                'get_id'=>'1',
                'get_type' => 'simple',
                'needs_shipping' => true,
                'get_sku'=>5,
                'is_taxable'=>true,
            ]
        );

        return $item;
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrderItem()
    {
        $item = new \WC_Order_Item_Product();

        $item['quantity'] = 1;
        $item['variation_id'] = null;
        $item['product_id'] = 1;
        $item['line_subtotal_tax']= 0;
        $item['line_total']= 20;
        $item['line_subtotal']= 20;
        $item['line_tax']= 0;
        $item['tax_status']= '';
        $item['total']= 20;
        $item['name']= 'productName';


        return $item;
    }
    private function expectedRequestData(){

        //we are not adding shipping address cause as not all fields are set(is mocked)
        // it will not show in the expected behavior
        return [
            'amount' => [
                'currency' => 'EUR',
                'value' => '20'
            ],
            'redirectUrl' =>
                'https://webshop.example.org/wc-api/mollie_return?order_id=1&key=wc_order_hxZniP1zDcnM8',
            'webhookUrl' =>
                'https://webshop.example.org/wc-api/mollie_return?order_id=1&key=wc_order_hxZniP1zDcnM8',
            'method' =>
                'ideal',
            'payment' =>
                [
                    'issuer' => 'ideal_INGBNL2A'
                ],
            'locale' => 'en_US',
            'billingAddress' => 'billingAddressHere',
            'metadata' =>
                [
                    'order_id' => 1,
                    'order_number' => 1
                ],
            'lines' =>
                [
                    [
                        "sku" => "5",
                        "name" => "",
                        "quantity" => 1,
                        "vatRate" => 0,
                        "unitPrice" =>
                            [
                                "currency" => "EUR",
                                "value" => 20
                            ],
                        "totalAmount" =>
                            [
                                "currency" => "EUR",
                                "value" => 20
                            ],
                        "vatAmount" =>
                            [
                                "currency" => "EUR",
                                "value" => 0
                            ],
                        "discountAmount" =>
                            [
                                "currency" => "EUR",
                                "value" => 0
                            ],
                        "metadata" =>
                            [
                                "order_item_id" => null
                            ]
                    ],
                    [
                        'type' => 'surcharge',
                        'name' => 'productName',
                        'quantity' => 1,
                        'vatRate' => 0,
                        'unitPrice' =>
                            [
                                'currency' => 'EUR',
                                'value' => 20,
                            ],
                        'totalAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 20
                            ],
                        'vatAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0
                            ],
                        'metadata' =>
                            [
                                'order_item_id' => null
                            ]
                    ],
                    [
                        'type' => 'gift_card',
                        'name' => NULL,
                        'unitPrice' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0,
                            ],
                        'vatRate' => 0,
                        'quantity' => 1,
                        'totalAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0
                            ],
                        'vatAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0
                            ]
                    ]
                ],
            'orderNumber' => 1
        ];
    }
}
