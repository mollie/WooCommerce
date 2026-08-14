<?php


namespace Mollie\WooCommerceTests\Functional\Shared;


use Mockery;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
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
        $expectedLabel = 'Gateway Fee';
        $expectedAmount =  10.00;
        $newTotal = 20.00;
        $expectedData = [
            'amount' => $expectedAmount,
            'name' => $expectedLabel,
            'currency' => 'EUR',
            'newTotal' => $newTotal,
        ];
        expect('get_option')->andReturn(
            'Gateway Fee', $this->helperMocks->paymentMethodSettings(
                [
                    'payment_surcharge' => $paymentSurcharge,
                    'surcharge_limit' => $feeLimit,
                    'fixed_fee' => $fixedFee,
                    'percentage' => $percentage,
                ]
            )
        );
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['verifyNonce', 'canProcessOrder', 'canProcessGateway', 'orderRemoveFee', 'orderAddFee']
        )->getMock();
        $testee->initializeGatewayFeeLabel();
        $testee->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);
        $testee->expects($this->once())
            ->method('canProcessOrder')
            ->willReturn($this->wcOrder(1,'key1'));

        $testee->expects($this->once())
            ->method('canProcessGateway')
            ->willReturn('mollie_wc_gateway_ideal');
        expect('wc_tax_enabled')->andReturn(false);
        //this method uses all woo functions outside our scope
        $testee->expects($this->once())
            ->method('orderRemoveFee');

        //this method uses all woo functions outside our scope
        $testee->expects($this->once())
            ->method('orderAddFee');
        expect('get_woocommerce_currency_symbol')->andReturn('EUR');

        expect('wp_send_json_success')->with($expectedData);
        $testee->updateSurchargeOrderPay();
    }

    /**
     *
     * GIVEN the woocommerce_order_item_meta_end hook fires with an object that doesn't
     * implement get_order_key() (e.g. WC_Order_Refund during PDF credit note generation)
     * WHEN renderHiddenOrderKeyFields is invoked with that object as $order
     * THEN it returns without fatally erroring and without rendering any output
     *
     * @test
     */
    public function rendersNothingWhenOrderDoesNotSupportGetOrderKey()
    {
        $testee = new GatewaySurchargeHandler(new Surcharge());
        $orderWithoutOrderKey = new \stdClass();

        $testee->renderHiddenOrderKeyFields(1, null, $orderWithoutOrderKey, false);

        $this->expectOutputString('');
    }

    protected function cartMock()
    {
        return $this->createConfiguredMock(
            'WC_Cart',
            [
                'get_subtotal'=> '2.00',
                'get_subtotal_tax' => '2.50',
            ]
        );
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
                'get_total' => 20.00,
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
            MolliePaymentGatewayHandler::class,
            [
                'getSelectedIssuer' => 'ideal_INGBNL2A',
                'get_return_url' => 'https://webshop.example.org/wc-api/',
            ]
        );
        return $gateway;
    }

    /**
     *
     * GIVEN WPML String Translation is active and a translation exists
     * WHEN surchargeFeeOption() reads the stored gatewayFeeLabel option
     * THEN it registers the raw value with icl_register_string and returns the WPML-translated label
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function surchargeFeeOptionReturnsWpmlTranslatedLabelWhenAvailable()
    {
        require_once TEST_PATH . '/../overrides/wpml.php';

        expect('get_option')
            ->once()
            ->with('mollie-payments-for-woocommerce_gatewayFeeLabel', Mockery::any())
            ->andReturn('Gateway Fee');
        expect('icl_register_string')
            ->once()
            ->with('mollie-payments-for-woocommerce', 'gatewayFeeLabel', 'Gateway Fee');
        expect('apply_filters')
            ->once()
            ->with('wpml_translate_single_string', 'Gateway Fee', 'mollie-payments-for-woocommerce', 'gatewayFeeLabel')
            ->andReturn('Kosten betaalmethode');

        $testee = $this->buildTesteeMock(GatewaySurchargeHandler::class, [new Surcharge()], [])->getMock();

        $this->assertSame('Kosten betaalmethode', $this->invokeProtectedMethod($testee, 'surchargeFeeOption'));
    }

    /**
     *
     * GIVEN WPML is not active (icl_register_string is unavailable)
     * WHEN surchargeFeeOption() reads the stored gatewayFeeLabel option
     * THEN it returns the raw option value unchanged, same as before the WPML integration
     *
     * @test
     */
    public function surchargeFeeOptionReturnsRawLabelWhenWpmlUnavailable()
    {
        expect('get_option')
            ->once()
            ->with('mollie-payments-for-woocommerce_gatewayFeeLabel', Mockery::any())
            ->andReturn('Gateway Fee');
        expect('apply_filters')
            ->with('wpml_translate_single_string', 'Gateway Fee', 'mollie-payments-for-woocommerce', 'gatewayFeeLabel')
            ->andReturnArg(1);

        $testee = $this->buildTesteeMock(GatewaySurchargeHandler::class, [new Surcharge()], [])->getMock();

        $this->assertSame('Gateway Fee', $this->invokeProtectedMethod($testee, 'surchargeFeeOption'));
    }

    /**
     *
     * GIVEN a fresh GatewaySurchargeHandler is constructed
     * WHEN it registers its WordPress hooks
     * THEN initializeGatewayFeeLabel is hooked on init (not after_setup_theme), so the WPML
     * language context is available before the label is fetched and cached
     *
     * @test
     */
    public function initializeGatewayFeeLabelHookMovesFromAfterSetupThemeToInit()
    {
        $hooks = (object) ['actions' => []];
        expect('add_action')
            ->andReturnUsing(static function () use ($hooks): bool {
                $args = func_get_args();
                $hook = $args[0];
                $callback = $args[1];
                if (!isset($hooks->actions[$hook])) {
                    $hooks->actions[$hook] = [];
                }
                $hooks->actions[$hook][] = $callback;
                return true;
            });

        $testee = new GatewaySurchargeHandler(new Surcharge());

        $this->assertArrayNotHasKey(
            'after_setup_theme',
            $hooks->actions,
            'GatewaySurchargeHandler must not hook initializeGatewayFeeLabel on after_setup_theme anymore'
        );
        $this->assertArrayHasKey('init', $hooks->actions);
        $this->assertContains(
            [$testee, 'initializeGatewayFeeLabel'],
            $hooks->actions['init'],
            'initializeGatewayFeeLabel must be hooked on init'
        );
    }

    private function invokeProtectedMethod($object, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        return $reflection->invokeArgs($object, $args);
    }
}
