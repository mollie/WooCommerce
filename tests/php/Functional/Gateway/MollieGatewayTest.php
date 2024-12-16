<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Gateway;

use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\MandateCollection;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\WooCommerceMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;


/**
 * Class Mollie_WC_Plugin_Test
 */
class MollieGatewayTest extends TestCase
{
    /** @var HelperMocks */
    private $helperMocks;
    /**
     * @var WooCommerceMocks
     */
    protected $wooCommerceMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
        $this->wooCommerceMocks = new WooCommerceMocks();
    }

    /**
     * WHEN gateway setting 'enabled' !== 'yes'
     * THEN is_available returns false
     * @test
     */
    public function gatewayNOTEnabledIsNOTAvailable()
    {
        $testee = $this->buildTestee(['enabled'=>'no']);

        $expectedResult = false;
        $result = $testee->is_available();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * WHEN gateway setting 'enabled' !== 'yes'
     * THEN is_available returns true
     * @test
     */
    public function gatewayEnabledIsAvailable()
    {
        $testee = $this->buildTestee(['enabled'=>'yes']);
        $total = 10.00;
        $WC = $this->wooCommerceMocks->wooCommerce(10.00, 0, $total, 0);
        expect('WC')->andReturn($WC);
        $testee->expects($this->atLeast(2))->method('get_order_total')->willReturn($total);
        expect('get_woocommerce_currency')->andReturn('EUR');
        expect('get_transient')->andReturn([['id'=>'ideal']]);
        expect('wc_get_base_location')->andReturn(['country'=>'ES']);

        $expectedResult = true;
        $result = $testee->is_available();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * WHEN gateway setting 'enabled' !== 'yes'
     * AND the customer has no country set
     * THEN we fallback to the shop country and is_available returns true
     * @test
     */
    public function gatewayAvailableWhenNoCountrySelected()
    {
        $testee = $this->buildTestee(['enabled'=>'yes']);
        $total = 10.00;
        $WC = $this->wooCommerceMocks->wooCommerce(10.00, 0, $total, 0, '');
        expect('WC')->andReturn($WC);
        $testee->expects($this->atLeast(2))->method('get_order_total')->willReturn($total);
        expect('get_woocommerce_currency')->andReturn('EUR');
        expect('get_transient')->andReturn([['id'=>'ideal']]);
        expect('wc_get_base_location')->andReturn(['country'=>'ES']);

        $expectedResult = true;
        $result = $testee->is_available();
        $this->assertEquals($expectedResult, $result);
    }

    private function buildTestee($settings){
        return $this->helperMocks->mollieGatewayBuilder('Ideal', false, false, $settings);
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



