<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\WC_Order_Item_Product;
use Mollie\WooCommerceTests\Stubs\WC_Settings_API;
use Mollie\WooCommerceTests\TestCase;



use stdClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Plugin_Test
 */
class PaymentServiceTest extends TestCase
{
    /** @var HelperMocks */
    private $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }

    /**
     * GIVEN I RECEIVE A WC ORDER (DIFFERENT KIND OF WC PRODUCTS OR SUBSCRIPTIONS)
     * WHEN I PAY WITH ANY GATEWAY (STATUS PAID, AUTHORIZED)
     * THEN CREATES CORRECT MOLLIE REQUEST ORDER (THE EXIT TO THE API IS TESTED)
     * THEN THE DEBUG LOGS ARE CORRECT
     * THEN THE ORDER NOTES ARE CREATED
     * THEN THE RESPONSE FROM MOLLIE IS PROCESSED (STATUS COMPLETED)
     * (THE RESPONSE FROM API IS MOCKED)
     * THEN THE STATUS OF THE WC ORDER IS AS EXPECTED (CHECK THE DB OR TEST THE EXIT)
     * THEN THE REDIRECTION FOR THE USER IS TO THE CORRECT PAGE
     * (PAY AGAIN OR ORDER COMPLETED) SO TEST THE ULR (POLYLANG…)
     *
     * @test
     */
    public function processPayment_Order_success(){
        $paymentMethodId = 'Ideal';
        $isSepa = true;
        $wcOrderId = 1;
        $wcOrderKey = 'wc_order_hxZniP1zDcnM8';
        $wcOrder = $this->wcOrder($wcOrderId, $wcOrderKey);
        $mollieOrderId = 'wvndyu';//ord_wvndyu
        $processPaymentRedirect = 'https://www.mollie.com/payscreen/order/checkout/'. $mollieOrderId;

        $paymentMethod = $this->helperMocks->paymentMethodBuilder($paymentMethodId);
        $orderEndpoints = $this->createConfiguredMock(
            OrderEndpoint::class,
            [
                'create' => new MollieOrderResponse(),
            ]
        );
        $apiClientMock = $this->createConfiguredMock(
            MollieApiClient::class,
            []
        );
        $apiClientMock->orders = $orderEndpoints;
        $voucherDefaultCategory = Voucher::NO_CATEGORY;
        $testee = new PaymentService(
            $this->helperMocks->noticeMock(),
            $this->helperMocks->loggerMock(),
            $this->helperMocks->paymentFactory($apiClientMock),
            $this->helperMocks->dataHelper($apiClientMock),
            $this->helperMocks->apiHelper($apiClientMock),
            $this->helperMocks->settingsHelper(),
            $this->helperMocks->pluginId(),
            $this->paymentCheckoutService($apiClientMock),
            $voucherDefaultCategory
        );
        $testee->setGateway($this->createMock(MolliePaymentGateway::class));
        stubs(
            [
                'admin_url' => 'http://admin.com',
                'wc_get_order' => $wcOrder,
                'wc_get_product' => $this->wcProduct(),
                'wc_get_payment_gateway_by_order' => $this->mollieGateway($paymentMethodId, $testee),
                'add_query_arg' => 'https://webshop.example.org/wc-api/mollie_return?order_id=1&key=wc_order_hxZniP1zDcnM8',
                'WC' => $this->wooCommerce()
            ]
        );
        $expectedRequestToMollie = $this->expectedRequestData($wcOrder);
        $orderEndpoints->method('create')->with($expectedRequestToMollie);

        /*
         *  Expectations
         */
        expect('is_plugin_active')
            ->andReturn(false);
        expect('get_option')
            ->with('mollie-payments-for-woocommerce_api_switch')
            ->andReturn(false);
        expect('get_transient')->andReturn(['ideal'=>['id'=>'ideal']]);
        $wcOrder->expects($this->any())
            ->method('get_billing_company')
            ->willReturn('');
        /*
        * Execute Test
        */
        $expectedResult = array (
            'result'   => 'success',
            'redirect' => $processPaymentRedirect,
        );
        $arrayResult = $testee->processPayment(1, $wcOrder, $paymentMethod, $processPaymentRedirect);
        self::assertEquals($expectedResult, $arrayResult);
    }


    protected function setUp(): void
    {
        $_POST = [];
        parent::setUp();

        when('__')->returnArg(1);
    }
    protected function mollieGateway($paymentMethodName, $testee, $isSepa = false, $isSubscription = false){
        $gateway = $this->createConfiguredMock(
            MolliePaymentGateway::class,
            [
                'getSelectedIssuer' => 'ideal_INGBNL2A',
                'get_return_url' => 'https://webshop.example.org/wc-api/',
            ]
        );
        $gateway->paymentMethod = $this->helperMocks->paymentMethodBuilder($paymentMethodName, $isSepa, $isSubscription);
        $gateway->paymentService = $testee;

        return $gateway;
    }


    /**
     *
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrder($id, $orderKey)
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => $id,
                'get_order_key' => $orderKey,
                'get_total' => '20',
                'get_items' => [$this->wcOrderItem()],
                'get_billing_first_name' => 'billingggivenName',
                'get_billing_last_name' => 'billingfamilyName',
                'get_billing_email' => 'billingemail',
                'get_shipping_first_name' => 'shippinggivenName',
                'get_shipping_last_name' => 'shippingfamilyName',
                'get_billing_address_1' => 'shippingstreetAndNumber',
                'get_billing_address_2' => 'billingstreetAdditional',
                'get_billing_postcode' => 'billingpostalCode',
                'get_billing_city' => 'billingcity',
                'get_billing_state' => 'billingregion',
                'get_billing_country' => 'billingcountry',
                'get_shipping_address_1' => 'shippingstreetAndNumber',
                'get_shipping_address_2' => 'shippingstreetAdditional',
                'get_shipping_postcode' => 'shippingpostalCode',
                'get_shipping_city' => 'shippingcity',
                'get_shipping_state' => 'shippingregion',
                'get_shipping_country' => 'shippingcountry',
                'get_shipping_methods' => false,
                'get_order_number' => 1,
                'get_payment_method' => 'mollie_wc_gateway_ideal',
                'get_currency' => 'EUR',
            ]
        );

        return $item;
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce() {
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
                'is_taxable'=>true
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
    private function expectedRequestData($order){

        //we are not adding shipping address cause as not all fields are set(is mocked)
        // it will not show in the expected behavior
        return [
            'amount' => [
                'currency' => 'EUR',
                'value' => '20.00'
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
            'billingAddress' => $this->billingAddress($order),
            'shippingAddress' => $this->shippingAddress($order),
            'metadata' =>
                [
                    'order_id' => 1,
                    'order_number' => 1
                ],
            'lines' =>
                [
                    [
                        "sku" => "5",
                        "name" => "productName",
                        "quantity" => 1,
                        "vatRate" => 0,
                        "unitPrice" =>
                            [
                                "currency" => "EUR",
                                "value" => 20.00
                            ],
                        "totalAmount" =>
                            [
                                "currency" => "EUR",
                                "value" => 20.00
                            ],
                        "vatAmount" =>
                            [
                                "currency" => "EUR",
                                "value" => 0.00
                            ],
                        "discountAmount" =>
                            [
                                "currency" => "EUR",
                                "value" => 0.00
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
                                'value' => 20.00,
                            ],
                        'totalAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 20.00
                            ],
                        'vatAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0.00
                            ],
                        'metadata' =>
                            [
                                'order_item_id' => null
                            ]
                    ],
                    [
                        'type' => 'gift_card',
                        'name' => 'productName',
                        'unitPrice' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0.00,
                            ],
                        'vatRate' => 0,
                        'quantity' => 1,
                        'totalAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0.00
                            ],
                        'vatAmount' =>
                            [
                                'currency' => 'EUR',
                                'value' => 0.00
                            ]
                    ]
                ],
            'orderNumber' => 1
        ];
    }
    protected function paymentMethodBuilder($paymentMethodName, $isSepa = false, $isSubscription = false)
    {
        return $this->helperMocks->paymentMethodBuilder($paymentMethodName, $isSepa, $isSubscription);
    }

    protected function paymentMethodMergedProperties($paymentMethodName, $isSepa, $isSubscription)
    {
        return $this->helperMocks->paymentMethodMergedProperties($paymentMethodName, $isSepa, $isSubscription);
    }
    protected function gatewayMockedOptions(string $paymentMethodId, $isSepa = false, $isSubscription = false)
    {
        return $this->helperMocks->gatewayMockedOptions($paymentMethodId, $isSepa, $isSubscription);
    }

    protected function iconFactoryMock()
    {
        return $this->getMockBuilder(IconFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function billingAddress($order){
        // Setup billing and shipping objects
        $billingAddress = new stdClass();

        $billingAddress->givenName = $order->get_billing_first_name();
        $billingAddress->familyName = $order->get_billing_last_name();
        $billingAddress->email =$order->get_billing_email();
        $billingAddress->streetAndNumber = $order->get_billing_address_1();
        $billingAddress->streetAdditional = $order->get_billing_address_2();
        $billingAddress->postalCode = $order->get_billing_postcode();
        $billingAddress->city = $order->get_billing_city();
        $billingAddress->region = $order->get_billing_state();
        $billingAddress->country = $order->get_billing_country();
        $billingAddress->organizationName = $order->get_billing_company();

        return $billingAddress;
    }

    protected function shippingAddress($order){
        $shippingAddress = new stdClass();

        $shippingAddress->givenName = $order->get_shipping_first_name();
        $shippingAddress->familyName = $order->get_shipping_last_name();
        $shippingAddress->email = $order->get_billing_email();
        $shippingAddress->streetAndNumber =
                $order->get_shipping_address_1();
        $shippingAddress->streetAdditional =
                $order->get_shipping_address_2();
        $shippingAddress->postalCode =
                $order->get_shipping_postcode();
        $shippingAddress->city =
                $order->get_shipping_city();
        $shippingAddress->region =
                $order->get_shipping_state();
        $shippingAddress->country =
                $order->get_shipping_country();
        return $shippingAddress;
    }

    protected function paymentCheckoutService($apiClientMock)
    {
        $data = $this->helperMocks->dataHelper($apiClientMock);
        return new PaymentCheckoutRedirectService($data);
    }


}



class MollieOrderResponse
{
    public $resource;
    public $id;
    public $mode;
    public $method;
    public $metadata;

    /**
     * MollieOrder constructor.
     * @param $resource
     */
    public function __construct($resource = 'order')
    {
        $this->resource = $resource;
        $this->id = 'mollieOrderId';
        $this->mode = 'test';
        $this->method = 'ideal';
        $this->metadata = new stdClass();
        $this->metadata->order_id = $this->id;
    }

    public function getCheckoutUrl()
    {
        return 'https://www.mollie.com/payscreen/order/checkout/wvndyu';
    }

}



