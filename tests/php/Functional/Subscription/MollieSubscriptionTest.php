<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Subscription;

use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;



use stdClass;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

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
    /*public function renewSubcriptionPaymentTest()
    {
        $wcSubscription = $this->wcOrder();


        $testee = $this->buildTesteeMock(
            MollieSubscriptionGateway::class,
            [],
            []
        )->getMock();
        var_dump($testee);
        $expectedResult = ['result' => 'success'];
        $result = $testee->scheduled_subscription_payment(1.02, $wcSubscription);
        $this->assertEquals($expectedResult, $result);
    }

    private function buildTestee(){
        $paymentMethod = $this->helperMocks->paymentMethodBuilder('Ideal');
        $paymentService = $this->helperMocks->paymentService();
        $orderInstructionsService = $this->helperMocks->orderInstructionsService();
        $mollieOrderService = $this->helperMocks->mollieOrderService();
        $data = $this->helperMocks->dataHelper();
        $logger = $this->helperMocks->loggerMock();
        $notice = $this->helperMocks->noticeMock();

        return new MollieSubscriptionGateway(
            $paymentMethod,
            $paymentService,
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
        );
    }*/

    private function wcOrder()
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => 1,
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



