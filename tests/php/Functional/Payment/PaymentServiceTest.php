<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\Ideal;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Mollie\WooCommerceTests\Stubs\Status;
use Mollie\WooCommerceTests\Stubs\WC_Order_Item_Product;
use Mollie\WooCommerceTests\Stubs\WC_Settings_API;
use Mollie\WooCommerceTests\TestCase;
use PHPUnit_Framework_Exception;
use Psr\Log\LoggerInterface;

use stdClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Plugin_Test
 */
class PaymentServiceTest extends TestCase
{
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

        $paymentMethod = $this->paymentMethodBuilder($paymentMethodId);
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
        $testee = new PaymentService(
            $this->noticeMock(),
            $this->loggerMock(),
            $this->paymentFactory($apiClientMock),
            $this->dataHelper($apiClientMock),
            $this->apiHelper($apiClientMock),
            $this->settingsHelper(),
            $this->pluginId(),
            $this->paymentCheckoutService($apiClientMock)
        );
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
        expect('get_option')
            ->with('mollie-payments-for-woocommerce_api_switch')
            ->andReturn(false);

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

    protected function setUp()
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
        $gateway->paymentMethod = $this->paymentMethodBuilder($paymentMethodName, $isSepa, $isSubscription);
        $gateway->paymentService = $testee;

        return $gateway;
    }

    protected function dataHelper($apiClientMock){
        $apiHelper = $this->apiHelper($apiClientMock);
        $logger = $this->loggerMock();
        $pluginId = $this->pluginId();
        $pluginPath = $this->pluginPath();
        $settings = $this->settingsHelper();
        return new Data($apiHelper, $logger, $pluginId, $settings, $pluginPath);
    }
    protected function apiHelper($apiClientMock)
    {
        $api = $this->createPartialMock(
            Api::class,
            ['getApiClient']
        );


        $api->method('getApiClient')->willReturn($apiClientMock);
        return $api;

    }
    protected function settingsHelper()
    {
        return $this->createConfiguredMock(
            Settings::class,
            [
                'isTestModeEnabled' => 'true',
                'getApiKey' => 'test_NtHd7vSyPSpEyuTEwhjsxdjsgVG4SV',
                'getPaymentLocale' => 'en_US',
                'shouldStoreCustomer' => false,
            ]
        );

    }

    protected function pluginId()
    {
        return 'mollie-payments-for-woocommerce';
    }
    protected function pluginVersion()
    {
        return '7.0.0';
    }
    protected function pluginPath()
    {
        return 'plugin/path';
    }
    protected function pluginUrl()
    {
        return 'https://pluginUrl.com';
    }
    protected function statusHelper()
    {
        return new Status();
    }

    protected function paymentFactory($apiClientMock){
        return new PaymentFactory(
            $this->dataHelper($apiClientMock),
            $this->apiHelper($apiClientMock),
            $this->settingsHelper(),
            $this->pluginId(),
            $this->loggerMock()
        );
    }

    protected function noticeMock()
    {
        return $this->getMockBuilder(AdminNotice::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
    protected function loggerMock()
    {
        return new emptyLogger();
    }
    /**
     *
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrder($id, $orderKey)
    {
        $item = $this->createConfiguredMock(
            'Mollie\WooCommerceTests\Stubs\WC_Order',
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
    private function wooCommerce() {
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
            'Mollie\WooCommerceTests\Stubs\WC_Product',
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
        $item = new WC_Order_Item_Product();

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
    protected function paymentMethodBuilder($paymentMethodName, $isSepa = false, $isSubscription = false){

        $paymentMethod= $this->createPartialMock(
            Ideal::class,
            ['getConfig', 'getInitialOrderStatus', 'getMergedProperties']
        );
        $paymentMethod
            ->method('getConfig')
            ->willReturn(
                $this->gatewayMockedOptions($paymentMethodName, $isSepa, $isSubscription)
            );
        $paymentMethod
            ->method('getInitialOrderStatus')
            ->willReturn('paid');
        $paymentMethod
            ->method('getMergedProperties')
            ->willReturn($this->paymentMethodMergedProperties($paymentMethodName, $isSepa, $isSubscription));

        return $paymentMethod;
    }

    protected function paymentMethodMergedProperties($paymentMethodName, $isSepa, $isSubscription){
        $options = $this->gatewayMockedOptions($paymentMethodName, $isSepa, $isSubscription);
        $settings = [
            'enabled' => 'yes',
            'title' => 'default title',
            'description' => 'default description',
            'display_logo' =>  'yes',
            'iconFileUrl' => '',
            'iconFilePath' => '',
            'allowed_countries' =>  [],
            'enable_custom_logo' => false,
            'payment_surcharge' =>  'no_fee',
            'fixed_fee' => '0.00',
            'percentage' =>  '0.00',
            'surcharge_limit' => '0.00',
            'maximum_limit' => '0.00',
            'activate_expiry_days_setting' => 'no',
            'order_dueDate' => '0',
            'issuers_dropdown_shown' => 'yes',
            'issuers_empty_option' => 'Select your bank',
            'initial_order_status' => 'on-hold',

        ];
        return array_merge($options, $settings);
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
        $data = $this->dataHelper($apiClientMock);
        return new PaymentCheckoutRedirectService($data);
    }

    protected function gatewayMockedOptions(string $paymentMethodId, $isSepa = false, $isSubscription = false)
    {
        return [
            'id' => strtolower($paymentMethodId),
            'defaultTitle' => __($paymentMethodId, 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => $isSepa,
            'Subscription' => $isSubscription
        ];
    }
}


class emptyLogger implements LoggerInterface{

    public function emergency($message, array $context = array())
    {
        // TODO: Implement emergency() method.
    }

    public function alert($message, array $context = array())
    {
        // TODO: Implement alert() method.
    }

    public function critical($message, array $context = array())
    {
        // TODO: Implement critical() method.
    }

    public function error($message, array $context = array())
    {
        // TODO: Implement error() method.
    }

    public function warning($message, array $context = array())
    {
        // TODO: Implement warning() method.
    }

    public function notice($message, array $context = array())
    {
        // TODO: Implement notice() method.
    }

    public function info($message, array $context = array())
    {
        // TODO: Implement info() method.
    }

    public function debug($message, array $context = array())
    {
        // TODO: Implement debug() method.
    }

    public function log($level, $message, array $context = array())
    {
        // TODO: Implement log() method.
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



