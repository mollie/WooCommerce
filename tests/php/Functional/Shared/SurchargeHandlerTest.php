<?php


namespace php\Functional\Shared;


use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;

class SurchargeHandlerTest extends TestCase
{
    protected $pluginUrl;
    /** @var HelperMocks */
    private $helperMocks;
    /**
     * @var string
     */
    protected $pluginPath;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }
    /**
     * with all surcharge types
     * GIVEN I'm in the checkout and the surcharge is set
     * WHEN the cart is above the fee minimum
     * THEN the cart will call add_fee with the proper fee name and amount
     *
     * @test
     */
    public function addsSurchargeFeesInCheckout(){
        $cart = $this->cartMock();
        $paymentSurcharge = Surcharge::FIXED_FEE;
        $fixedFee = 10.00;
        $percentage = 0;
        $feeLimit = 1;
        $expectedLabel = 'custom label';
        $expectedAmount = 10.00;
        $testee = $this->createPartialMock(
            GatewaySurchargeHandler::class,
            []
        );
        $testee->gatewayFeeLabel = 'custom label';

        expect('mollieWooCommerceIsCheckoutContext')->andReturn(true);
        expect('WC')->andReturn($this->wooCommerce());
        expect('get_option')->andReturn(
            $this->helperMocks->paymentMethodSettings(
                [
                    'payment_surcharge' => $paymentSurcharge,
                    'surcharge_limit' => $feeLimit,
                    'fixed_fee' => $fixedFee,
                    'percentage' => $percentage,
                ]
            )
        );

        $cart->expects(self::once())->method('add_fee')->with($expectedLabel, $expectedAmount);
        $testee->add_engraving_fees($cart);
    }

    /**
     *
     * GIVEN I'm in the checkout and the surcharge is set
     * WHEN the cart is above the fee minimum
     * THEN the cart will call add_fee with the proper fee name and amount
     *
     * @test
     */
    public function addsSurchargeFeesInOrderPayPage()
    {
        $paymentSurcharge = Surcharge::FIXED_FEE;
        $fixedFee = 10.00;
        $percentage = 0;
        $feeLimit = 1;
        $expectedLabel = 'custom label';
        $expectedAmount = 10.00;
        $newTotal = 20.00;
        $expectedData = [
            'amount' => $expectedAmount,
            'name' => $expectedLabel,
            'currency' => 'EUR',
            'newTotal' => $newTotal,
        ];
        $testee = $this->createPartialMock(
            GatewaySurchargeHandler::class,
            ['canProcessOrder', 'canProcessGateway', 'orderRemoveFee', 'orderAddFee']
        );
        $testee->gatewayFeeLabel = 'custom label';

        $testee->expects($this->once())
            ->method('canProcessOrder')
            ->willReturn($this->wcOrder(1,'key1'));

        $testee->expects($this->once())
            ->method('canProcessGateway')
            ->willReturn('mollie_wc_gateway_ideal');
        //this method uses all woo functions outside our scope
        $testee->expects($this->once())
            ->method('orderRemoveFee');
        expect('get_option')->andReturn(
            $this->helperMocks->paymentMethodSettings(
                [
                    'payment_surcharge' => $paymentSurcharge,
                    'surcharge_limit' => $feeLimit,
                    'fixed_fee' => $fixedFee,
                    'percentage' => $percentage,
                ]
            )
        );
        //this method uses all woo functions outside our scope
        $testee->expects($this->once())
            ->method('orderAddFee');
        expect('get_woocommerce_currency_symbol')->andReturn('EUR');

        expect('wp_send_json_success')->with($expectedData);
        $testee->updateSurchargeOrderPay();
    }

    protected function cartMock()
    {
        return $this->createConfiguredMock(
            'Mollie\WooCommerceTests\Stubs\WC_Cart',
            [
                'get_subtotal'=> '2.00',
                'get_subtotal_tax' => '2.50',
            ]
        );
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce() {
        $wc = $this->createConfiguredMock(
            'WooCommerce',
            [

            ]
        );
        $wc->session = new \WC_Session();
        $wc->session->chosen_payment_method = 'mollie_wc_gateway_ideal';
        return $wc;
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
                'get_total' => '20.00',
                'get_items' => [],
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
    protected function mollieGateway(){
        $gateway = $this->createConfiguredMock(
            MolliePaymentGateway::class,
            [
                'getSelectedIssuer' => 'ideal_INGBNL2A',
                'get_return_url' => 'https://webshop.example.org/wc-api/',
            ]
        );
        return $gateway;
    }
}
