<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Subscription;

use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\MandateCollection;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;


/**
 * Class Mollie_WC_Plugin_Test
 */
class MollieSubscriptionTest extends TestCase
{
    /** @var HelperMocks */
    private $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }

    /**
     * GIVEN I RECEIVE A WC ORDER WITH SUBSCRIPTION
     * THEN CREATES CORRECT MOLLIE REQUEST ORDER
     * THEN THE DEBUG LOGS ARE CORRECT
     * THEN THE ORDER NOTES ARE CREATED
     * @test
     */
    public function renewSubscriptionPaymentTest()
    {
        $gatewayName = 'mollie_wc_gateway_ideal';
        $renewalOrder = $this->wcOrder();
        $subscription = $this->wcOrder(2, $gatewayName, $renewalOrder, 'active' );
        $gateway = $this->helperMocks->genericPaymentGatewayMock();
        $testee = $this->buildTestee();

        expect('wcs_get_subscriptions_for_renewal_order')->andReturn(
            [$subscription]
        );
        $testee->expects($this->once())->method(
            'restore_mollie_customer_id_and_mandate'
        )->willReturn(false);
        expect('wc_get_payment_gateway_by_order')->andReturn($gateway);
        $renewalOrder->expects($this->once())->method(
            'set_payment_method'
        )->with($gatewayName);
        expect('wc_get_order')->with(1)->andReturn($renewalOrder);
        expect('wcs_order_contains_renewal')->with(1)->andReturn($renewalOrder);
        expect('wcs_get_subscription')->andReturn($subscription);

        $expectedResult = ['result' => 'success'];
        $result = $testee->scheduled_subscription_payment(1.02, $renewalOrder, $gateway);
        $this->assertEquals($expectedResult, $result);
    }

    private function buildTestee(){
        $paymentMethod = $this->helperMocks->paymentMethodBuilder('Ideal');
        $orderInstructionsService = $this->helperMocks->orderInstructionsService();
        $mollieOrderService = $this->helperMocks->mollieOrderService();
        $data = $this->helperMocks->dataHelper();
        $logger = $this->helperMocks->loggerMock();
        $notice = $this->helperMocks->noticeMock();
        $HttpResponseService = new HttpResponse();
        $settingsHelper = $this->helperMocks->settingsHelper();
        $mollieObject = $this->createMock(MollieObject::class);
        $apiClientMock = $this->helperMocks->apiClient();
        $mandate = $this->createMock(Mandate::class);
        $mandate->status = 'valid';
        $mandate->method = 'mollie_wc_gateway_ideal';
        $customer = $this->createConfiguredMock(
            Customer::class,
            [
                'mandates'=> [$mandate]
            ]
        );
        $apiClientMock->customers = $this->createConfiguredMock(
            CustomerEndpoint::class,
            [
                'get'=> $customer
            ]
        );
        $paymentResponse = $this->createMock(Payment::class);
        $paymentResponse->method = 'ideal';
        $paymentResponse->mandateId = 'mandateId';
        $paymentResponse->resource = 'payment';
        $apiClientMock->payments = $this->createConfiguredMock(
            PaymentEndpoint::class,
            [
                'create'=> $paymentResponse
            ]
        );
        $paymentFactory = $this->helperMocks->paymentFactory($apiClientMock);
        $pluginId = $this->helperMocks->pluginId();
        $apiHelper = $this->helperMocks->apiHelper($apiClientMock);
        return $this->buildTesteeMock(
            MollieSubscriptionGatewayHandler::class,
            [
                $paymentMethod,
                $orderInstructionsService,
                $mollieOrderService,
                $data,
                $logger,
                $notice,
                $HttpResponseService,
                $settingsHelper,
                $mollieObject,
                $paymentFactory,
                $pluginId,
                $apiHelper
            ],
            [
                'init_form_fields',
                'initDescription',
                'initIcon',
                'isTestModeEnabledForRenewalOrder',
                'restore_mollie_customer_id_and_mandate'
            ]
        )->getMock();
    }

    private function wcOrder($id = 1, $meta = false, $parentOrder = false, $status = 'processing')
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => $id,
                'get_order_key' => 'wc_order_hxZniP1zDcnM8',
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
                'get_meta' => $meta,
                'get_parent' => $parentOrder,
                'update_status'=>$status
            ]
        );

        return $item;
    }
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

}



