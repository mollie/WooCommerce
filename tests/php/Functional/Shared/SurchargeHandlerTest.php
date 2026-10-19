<?php


namespace Mollie\WooCommerceTests\Functional\Shared;


use Mockery;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

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

    /**
     *
     * GIVEN an order that no longer needs payment (e.g. processing or completed)
     * WHEN the order-pay surcharge request resolves the order
     * THEN the order is not processed
     *
     * @test
     */
    public function rejectsOrderThatNoLongerNeedsPayment()
    {
        $order = $this->createConfiguredMock(\WC_Order::class, ['needs_payment' => false]);
        when('wc_get_post_data_by_key')->justReturn('wc_order_key1');
        when('wc_get_order_id_by_order_key')->justReturn(1);
        when('wc_get_order')->justReturn($order);

        $testee = $this->buildTesteeMock(GatewaySurchargeHandler::class, [new Surcharge()], [])->getMock();

        $this->assertFalse($this->invokeProtectedMethod($testee, 'canProcessOrder'));
    }

    /**
     *
     * GIVEN an order awaiting payment
     * WHEN the order-pay surcharge request resolves the order
     * THEN the order is returned
     *
     * @test
     */
    public function acceptsOrderAwaitingPayment()
    {
        $order = $this->createConfiguredMock(\WC_Order::class, ['needs_payment' => true]);
        when('wc_get_post_data_by_key')->justReturn('wc_order_key1');
        when('wc_get_order_id_by_order_key')->justReturn(1);
        when('wc_get_order')->justReturn($order);

        $testee = $this->buildTesteeMock(GatewaySurchargeHandler::class, [new Surcharge()], [])->getMock();

        $this->assertSame($order, $this->invokeProtectedMethod($testee, 'canProcessOrder'));
    }

    /**
     *
     * GIVEN a posted gateway ID
     * WHEN it is not a registered, enabled gateway
     * THEN canProcessGateway rejects it; a registered, enabled gateway is accepted
     *
     * @test
     * @dataProvider postedGatewayProvider
     */
    public function acceptsOnlyRegisteredEnabledGateways(string $postedMethod, $expected)
    {
        $gateways = [
            'mollie_wc_gateway_creditcard' => new \WC_Payment_Gateway('mollie_wc_gateway_creditcard', 'yes'),
            'mollie_wc_gateway_bancontact' => new \WC_Payment_Gateway('mollie_wc_gateway_bancontact', 'no'),
            'bacs' => new \WC_Payment_Gateway('bacs', 'yes'),
        ];
        $this->mockRegisteredGateways($gateways);
        when('wc_clean')->returnArg();
        $_POST['method'] = $postedMethod;

        $testee = $this->buildTesteeMock(GatewaySurchargeHandler::class, [new Surcharge()], [])->getMock();

        $this->assertSame($expected, $this->invokeProtectedMethod($testee, 'canProcessGateway'));
        unset($_POST['method']);
    }

    public function postedGatewayProvider(): array
    {
        return [
            'enabled Mollie gateway' => ['mollie_wc_gateway_creditcard', 'mollie_wc_gateway_creditcard'],
            'enabled non-Mollie gateway' => ['bacs', 'bacs'],
            'disabled Mollie gateway' => ['mollie_wc_gateway_bancontact', false],
            'unregistered gateway' => ['mollie_wc_gateway_doesnotexist', false],
            'prefix not at the start' => ['xmollie_wc_gateway_creditcard', false],
            'empty' => ['', false],
        ];
    }

    /**
     *
     * GIVEN a gateway ID that does not start with the Mollie prefix
     * WHEN isMollieGateway checks it
     * THEN it is not treated as a Mollie gateway
     *
     * @test
     */
    public function isMollieGatewayRequiresPrefix()
    {
        $testee = $this->buildTesteeMock(GatewaySurchargeHandler::class, [new Surcharge()], [])->getMock();

        $this->assertTrue($this->invokeProtectedMethod($testee, 'isMollieGateway', ['mollie_wc_gateway_ideal']));
        $this->assertFalse($this->invokeProtectedMethod($testee, 'isMollieGateway', ['xmollie_wc_gateway_ideal']));
    }

    /**
     *
     * GIVEN the order-pay surcharge request is valid apart from the gateway
     * WHEN the posted gateway is rejected
     * THEN the order's fees are left untouched and an error is returned
     *
     * @test
     */
    public function leavesSurchargeUntouchedWhenGatewayIsRejected()
    {
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['verifyNonce', 'canProcessOrder', 'canProcessGateway', 'orderRemoveFee', 'orderAddFee']
        )->getMock();
        $testee->method('verifyNonce')->willReturn(true);
        $testee->method('canProcessOrder')->willReturn($this->wcOrder(1, 'key1'));
        $testee->method('canProcessGateway')->willReturn(false);
        $testee->expects($this->never())->method('orderRemoveFee');
        $testee->expects($this->never())->method('orderAddFee');
        $this->expectJsonErrorEndsRequest();
        expect('wp_send_json_success')->never();

        $testee->updateSurchargeOrderPay();
    }

    /**
     *
     * GIVEN the order-pay surcharge request is valid apart from the order state
     * WHEN the order no longer needs payment
     * THEN the order's fees are left untouched and an error is returned
     *
     * @test
     */
    public function leavesSurchargeUntouchedWhenOrderIsRejected()
    {
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['verifyNonce', 'canProcessOrder', 'canProcessGateway', 'orderRemoveFee', 'orderAddFee']
        )->getMock();
        $testee->method('verifyNonce')->willReturn(true);
        $testee->method('canProcessOrder')->willReturn(false);
        $testee->expects($this->never())->method('canProcessGateway');
        $testee->expects($this->never())->method('orderRemoveFee');
        $this->expectJsonErrorEndsRequest();

        $testee->updateSurchargeOrderPay();
    }

    /**
     *
     * GIVEN a valid order-pay request for an enabled gateway without a surcharge
     * WHEN the surcharge is updated
     * THEN an existing surcharge is removed and no new one is added
     *
     * @test
     */
    public function removesSurchargeForGatewayWithoutFee()
    {
        expect('get_option')->andReturn(
            'Gateway Fee',
            $this->helperMocks->paymentMethodSettings(['payment_surcharge' => Surcharge::NO_FEE])
        );
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['verifyNonce', 'canProcessOrder', 'canProcessGateway', 'orderRemoveFee', 'orderAddFee']
        )->getMock();
        $testee->initializeGatewayFeeLabel();
        $testee->method('verifyNonce')->willReturn(true);
        $testee->method('canProcessOrder')->willReturn($this->wcOrder(1, 'key1'));
        $testee->method('canProcessGateway')->willReturn('mollie_wc_gateway_bancontact');
        $testee->expects($this->once())->method('orderRemoveFee');
        $testee->expects($this->never())->method('orderAddFee');
        expect('get_woocommerce_currency_symbol')->andReturn('EUR');
        expect('wp_send_json_success')->once()->with([
            'amount' => false,
            'currency' => 'EUR',
            'newTotal' => 20.00,
        ]);

        $testee->updateSurchargeOrderPay();
    }

    /**
     *
     * GIVEN the order-pay form is submitted with a gateway
     * WHEN the gateway has a surcharge or the order already carries one
     * THEN the server recalculates the surcharge for that gateway before payment;
     * otherwise the order is left untouched
     *
     * @test
     * @dataProvider payActionProvider
     */
    public function recalculatesSurchargeOnPayAction(
        $gateway,
        bool $orderHasFee,
        bool $gatewayHasFee,
        bool $expectRecalculation
    ) {
        $order = $this->createConfiguredMock(\WC_Order::class, ['needs_payment' => true]);
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['canProcessGateway', 'orderHasSurchargeFee', 'gatewayHasSurcharge', 'applyOrderSurcharge']
        )->getMock();
        $testee->method('canProcessGateway')->with('payment_method')->willReturn($gateway);
        $testee->method('orderHasSurchargeFee')->willReturn($orderHasFee);
        $testee->method('gatewayHasSurcharge')->willReturn($gatewayHasFee);
        $testee->expects($expectRecalculation ? $this->once() : $this->never())
            ->method('applyOrderSurcharge')
            ->with($order, $gateway);

        $testee->recalculateSurchargeOnPayAction($order);
    }

    public function payActionProvider(): array
    {
        return [
            'surcharge gateway, no fee on order' => ['mollie_wc_gateway_creditcard', false, true, true],
            'no-surcharge gateway, fee on order' => ['mollie_wc_gateway_bancontact', true, false, true],
            'non-Mollie gateway, fee on order' => ['bacs', true, false, true],
            'no surcharge anywhere' => ['mollie_wc_gateway_bancontact', false, false, false],
            'invalid gateway' => [false, true, true, false],
        ];
    }

    /**
     *
     * GIVEN the order-pay form is submitted
     * WHEN the order no longer needs payment
     * THEN the surcharge is not recalculated
     *
     * @test
     */
    public function skipsPayActionForOrderNotNeedingPayment()
    {
        $order = $this->createConfiguredMock(\WC_Order::class, ['needs_payment' => false]);
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['canProcessGateway', 'applyOrderSurcharge']
        )->getMock();
        $testee->expects($this->never())->method('canProcessGateway');
        $testee->expects($this->never())->method('applyOrderSurcharge');

        $testee->recalculateSurchargeOnPayAction($order);
    }

    /**
     *
     * GIVEN the surcharge handler is set up on init
     * WHEN its actions are registered
     * THEN the surcharge is recalculated on the order-pay form and on both Store API checkout routes
     *
     * @test
     */
    public function registersServerSideRecalculationHooks()
    {
        $testee = new GatewaySurchargeHandler(new Surcharge());

        $testee->surchargeActions();

        $this->assertNotFalse(has_action('woocommerce_before_pay_action', [$testee, 'recalculateSurchargeOnPayAction']));
        $this->assertNotFalse(has_filter('rest_request_before_callbacks', [$testee, 'rememberStoreApiCheckout']));
        $this->assertNotFalse(has_action('woocommerce_store_api_checkout_order_processed', [$testee, 'recalculateSurchargeOnStoreApiOrderPay']));
    }

    /**
     *
     * GIVEN a gateway with a surcharge and a maximum order amount for it
     * WHEN the order total is above that maximum
     * THEN the old surcharge is removed and no new one is added
     *
     * @test
     */
    public function addsNoSurchargeAboveTheMaximumLimit()
    {
        expect('get_option')->andReturn(
            'Gateway Fee',
            $this->helperMocks->paymentMethodSettings([
                'payment_surcharge' => Surcharge::FIXED_FEE,
                'fixed_fee' => 1.5,
                'maximum_limit' => 10,
            ])
        );
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['orderRemoveFee', 'orderAddFee']
        )->getMock();
        $testee->initializeGatewayFeeLabel();
        $testee->expects($this->once())->method('orderRemoveFee');
        $testee->expects($this->never())->method('orderAddFee');

        $amount = $this->invokeProtectedMethod(
            $testee,
            'applyOrderSurcharge',
            [$this->wcOrder(1, 'key1'), 'mollie_wc_gateway_ideal']
        );

        $this->assertSame(0.0, $amount);
    }

    /**
     *
     * GIVEN a POST request to a Store API route
     * WHEN it is the checkout or the order-pay route
     * THEN the posted payment method, and the paid order, are remembered for this request
     *
     * @test
     * @dataProvider storeApiRouteProvider
     */
    public function remembersStoreApiCheckoutPaymentMethod(
        string $method,
        string $route,
        $expectedPaymentMethod,
        int $expectedOrderId
    ) {
        when('sanitize_text_field')->returnArg();
        when('absint')->alias('intval');
        $request = $this->restRequest($method, $route, 'mollie_wc_gateway_ideal');
        $testee = new GatewaySurchargeHandler(new Surcharge());

        $response = $testee->rememberStoreApiCheckout(null, [], $request);

        $this->assertNull($response);
        $this->assertSame($expectedPaymentMethod, $this->protectedProperty($testee, 'storeApiPaymentMethod'));
        $this->assertSame($expectedOrderId, $this->protectedProperty($testee, 'storeApiOrderPayId'));
    }

    public function storeApiRouteProvider(): array
    {
        return [
            'block checkout' => ['POST', '/wc/store/v1/checkout', 'mollie_wc_gateway_ideal', 0],
            'unversioned block checkout' => ['POST', '/wc/store/checkout', 'mollie_wc_gateway_ideal', 0],
            'order-pay' => ['POST', '/wc/store/v1/checkout/42', 'mollie_wc_gateway_ideal', 42],
            'checkout draft update' => ['PATCH', '/wc/store/v1/checkout', null, 0],
            'other Store API route' => ['POST', '/wc/store/v1/cart/add-item', null, 0],
        ];
    }

    /**
     *
     * GIVEN the session still holds the payment method chosen earlier
     * WHEN the Store API checkout posts another payment method
     * THEN the cart surcharge is calculated for the posted one
     *
     * @test
     */
    public function cartSurchargeUsesThePaymentMethodPostedToTheStoreApi()
    {
        when('sanitize_text_field')->returnArg();
        $session = $this->getMockBuilder(\WC_Session::class)->setMethods(['get'])->getMock();
        $session->method('get')->with('chosen_payment_method')->willReturn('mollie_wc_gateway_banktransfer');
        when('WC')->justReturn((object) ['session' => $session]);
        $testee = new GatewaySurchargeHandler(new Surcharge());

        $this->assertSame('mollie_wc_gateway_banktransfer', $this->invokeProtectedMethod($testee, 'chosenGateway'));

        $testee->rememberStoreApiCheckout(null, [], $this->restRequest('POST', '/wc/store/v1/checkout', 'mollie_wc_gateway_ideal'));

        $this->assertSame('mollie_wc_gateway_ideal', $this->invokeProtectedMethod($testee, 'chosenGateway'));
    }

    /**
     *
     * GIVEN an order paid through the Store API order-pay route
     * WHEN the order is the one paid by this request, with an enabled gateway
     * THEN its surcharge is recalculated for the order's payment method; otherwise it is left untouched
     *
     * @test
     * @dataProvider storeApiOrderPayProvider
     */
    public function recalculatesSurchargeOnStoreApiOrderPay(int $paidOrderId, bool $gatewayEnabled, bool $expectRecalculation)
    {
        $order = $this->createConfiguredMock(\WC_Order::class, [
            'get_id' => 42,
            'get_payment_method' => 'mollie_wc_gateway_ideal',
        ]);
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['isEnabledGateway', 'recalculateOrderSurcharge']
        )->getMock();
        $testee->method('isEnabledGateway')->with('mollie_wc_gateway_ideal')->willReturn($gatewayEnabled);
        $testee->expects($expectRecalculation ? $this->once() : $this->never())
            ->method('recalculateOrderSurcharge')
            ->with($order, 'mollie_wc_gateway_ideal');
        $this->setProtectedProperty($testee, 'storeApiOrderPayId', $paidOrderId);

        $testee->recalculateSurchargeOnStoreApiOrderPay($order);
    }

    public function storeApiOrderPayProvider(): array
    {
        return [
            'order paid by this request' => [42, true, true],
            'disabled gateway' => [42, false, false],
            'other order (block checkout)' => [0, true, false],
        ];
    }

    /**
     *
     * GIVEN the surcharge label was saved empty
     * WHEN fees are matched against it
     * THEN no fee is taken for the surcharge
     *
     * @test
     */
    public function emptyLabelMatchesNoFee()
    {
        $testee = new GatewaySurchargeHandler(new Surcharge());
        $this->setProtectedProperty($testee, 'gatewayFeeLabel', '');

        $this->assertFalse($this->invokeProtectedMethod($testee, 'isSurchargeFee', ['Handling fee']));

        $this->setProtectedProperty($testee, 'gatewayFeeLabel', 'Gateway Fee');

        $this->assertTrue($this->invokeProtectedMethod($testee, 'isSurchargeFee', ['Gateway Fee']));
        $this->assertFalse($this->invokeProtectedMethod($testee, 'isSurchargeFee', ['Handling fee']));
    }

    /**
     * wp_send_json_error() ends the request in production
     */
    private function expectJsonErrorEndsRequest(): void
    {
        expect('wp_send_json_error')->once()->andThrow(new \RuntimeException('wp_send_json_error'));
        $this->expectExceptionMessage('wp_send_json_error');
    }

    private function restRequest(string $method, string $route, string $paymentMethod)
    {
        $request = $this->getMockBuilder(\WP_REST_Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['get_method', 'get_route', 'get_param'])
            ->getMock();
        $request->method('get_method')->willReturn($method);
        $request->method('get_route')->willReturn($route);
        $request->method('get_param')->with('payment_method')->willReturn($paymentMethod);

        return $request;
    }

    private function protectedProperty($object, string $name)
    {
        $property = new \ReflectionProperty(GatewaySurchargeHandler::class, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property->getValue($object);
    }

    private function setProtectedProperty($object, string $name, $value): void
    {
        $property = new \ReflectionProperty(GatewaySurchargeHandler::class, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($object, $value);
    }

    private function mockRegisteredGateways(array $gateways): void
    {
        $paymentGateways = new class ($gateways) {
            private $gateways;

            public function __construct(array $gateways)
            {
                $this->gateways = $gateways;
            }

            public function payment_gateways(): array
            {
                return $this->gateways;
            }
        };
        $wc = new class ($paymentGateways) {
            private $paymentGateways;

            public function __construct($paymentGateways)
            {
                $this->paymentGateways = $paymentGateways;
            }

            public function payment_gateways()
            {
                return $this->paymentGateways;
            }
        };
        when('WC')->justReturn($wc);
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
