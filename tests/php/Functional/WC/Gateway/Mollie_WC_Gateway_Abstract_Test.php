<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\WC\Gateway;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerceTests\TestCase;

use Mollie_WC_Gateway_Ideal;
use Mollie_WC_Plugin;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use Faker;
use Faker\Generator;

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
        $issuer_id = Mollie_WC_Plugin::PLUGIN_ID . '_issuer_' . PaymentMethod::IDEAL;
        $_POST[$issuer_id]= 'ideal_INGBNL2A';
        stubs(
            [
                'wc_get_order_status_name' => 'wc-on-hold',
                'admin_url' => 'admin.php?page=wc-settings&tab=products&section=inventory',
            ]
        );
        $testee = new Mollie_WC_Gateway_Ideal();
        /*
         * Stubs
         */

        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
        $wcOrderId = $this->faker->uuid;
        $wcOrderKey = 'wc_order_hxZniP1zDcnM8';
        $mollieOrderId = 'wvndyu';//ord_wvndyu
        $processPaymentRedirect = 'https://www.mollie.com/payscreen/order/checkout/'. $mollieOrderId;
        $expectedResult = array (
            'result'   => 'success',
            'redirect' => $processPaymentRedirect,
        );
        stubs(
            [
                'wc_get_order' => $this->wcOrder($wcOrderId, $wcOrderKey),
                'get_locale' => 'en_US',
                'wc_get_payment_gateway_by_order' => $testee,
                'get_home_url' => 'https://webshop.example.org',
                'WC' => $this->wooCommerce(),
                'add_query_arg' => 'https://webshop.example.org/wc-api/mollie_return?order_id='.$wcOrderId.'&key='.$wcOrderKey,
                'is_plugin_active' => false
            ]
        );
        $expectedPaymentRequestData = [
            "amount" => [
                "currency" => "EUR",
                "value" => "20.00" // You must send the correct number of decimals, thus we enforce the use of strings
            ],
            "description" => "Order #12345",
            "redirectUrl" => "https://webshop.example.org/order/12345/",
            "webhookUrl" => "https://webshop.example.org/payments/webhook/",
            "metadata" => [
                "order_id" => $wcOrderId,
            ],
        ];


        /*
         *  Expectations
         */
        expect('mollieWooCommerceDebug')
            ->once()
            ->with("{$testee->id}: Start process_payment for order {$wcOrderId}");
        expect('get_option')
            ->once()
            ->andReturn(false);
        expect('wc_get_product')
            ->andReturn($this->wcProduct());
        expectedActionDone(Mollie_WC_Plugin::PLUGIN_ID . '_create_payment')
            ->once()
            ->with($expectedPaymentRequestData);
        /*
        * Execute Test
        */
        $arrayResult = $testee->process_payment($wcOrderId);
        self::assertEquals($expectedResult, $arrayResult);
    }

    protected function setUp()
    {
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
                'get_billing_first_name'=>'billingName',
                'get_billing_last_name'=>'billingLastName',
                'get_billing_email'=>'bill_email@email.com',
                'get_shipping_first_name'=>'shipName',
                'get_shipping_last_name'=>'shipLastName',
                'get_billing_address_1'=>'address1',
                'get_billing_address_2'=>'address2',
                'get_billing_postcode'=>'postCode',
                'get_billing_city'=>'city',
                'get_billing_state'=>'state',
                'get_billing_country'=>'country',
                'get_shipping_address_1'=>'shipaddress1',
                'get_shipping_address_2'=>'shipaddress2',
                'get_shipping_postcode'=>'shippostCode',
                'get_shipping_city'=>'shipcity',
                'get_shipping_state'=>'shipstate',
                'get_shipping_country'=>'shipcountry',
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
        $item = $this->createConfiguredMock(
            'WC_Order_Item_Product',
            [
                'get_name'=>'itemName',
                'get_item_quantity'=>1,
                'get_id'=>1
            ]
        );
        $item['quantity'] = 1;
        $item['variation_id'] = null;
        $item['product_id'] = 1;
        $item['line_subtotal_tax']= 0;


        return $item;
    }
}
