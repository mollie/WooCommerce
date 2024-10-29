<?php

namespace php\Functional\OrderFunctions;

use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\PluginApi\MolliePluginApi;
use Mollie\WooCommerceTests\TestCase;

use stdClass;
use function Mollie\WooCommerce\Inc\Api\mollie_capture_order;
use function Mollie\WooCommerce\Inc\Api\mollie_void_order;
use function Mollie\WooCommerce\Inc\Api\mollie_refund_order;
use function Mollie\WooCommerce\Inc\Api\mollie_cancel_order;
use function Mollie\WooCommerce\Inc\Api\mollie_ship_order;

use \WC_Order;

class MollieApiTest extends TestCase {

    /**
     * @runInSeparateProcess
     */
    public function test_captureOrder_invokes_capturePayment_with_correct_order() {
        $mockOrder = $this->createMock(WC_Order::class);
        $mockOrder->method('get_id')->willReturn('123');
        $capturePaymentMock = $this->getMockBuilder(stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $capturePaymentClosure = function ($orderId) use ($capturePaymentMock) {
            $capturePaymentMock->__invoke($orderId);
        };
        $capturePaymentMock->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo(123));

        $voidPaymentMock = function() {};
        $mollieObjectMock = $this->createMock(MollieObject::class);

        MolliePluginApi::init($capturePaymentClosure, $voidPaymentMock, $mollieObjectMock);
        mollie_capture_order($mockOrder);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_voidOrder_invokes_voidPayment_with_correct_order() {
        $mockOrder = $this->createMock(WC_Order::class);
        $mockOrder->method('get_id')->willReturn('123');
        $voidPaymentMock = $this->getMockBuilder(stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $voidPaymentClosure = function ($orderId) use ($voidPaymentMock) {
            $voidPaymentMock->__invoke($orderId);
        };
        $voidPaymentMock->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo(123));

        $capturePaymentMock = function() {};
        $mollieObjectMock = $this->createMock(MollieObject::class);

        MolliePluginApi::init($capturePaymentMock, $voidPaymentClosure, $mollieObjectMock);
        mollie_void_order($mockOrder);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_refundOrder_with_correct_order() {
        $mockOrder = $this->createMock(WC_Order::class);
        $mockOrder->method('get_id')->willReturn('123');
        $voidPaymentMock = function() {};
        $capturePaymentMock = function() {};
        $mollieObjectMock = $this->createConfiguredMock(MollieObject::class, [
            'processRefund' => true
        ]);
        $mollieObjectMock->expects($this->once())->method('processRefund')->with('123', 10.0, 'reason');

        MolliePluginApi::init($capturePaymentMock, $voidPaymentMock, $mollieObjectMock);
        mollie_refund_order($mockOrder, 10.0, 'reason');
    }

    /**
     * @runInSeparateProcess
     */
    public function test_cancelOrder_with_correct_order() {
        $mockOrder = $this->createMock(WC_Order::class);
        $mockOrder->method('get_id')->willReturn('123');
        $voidPaymentMock = function() {};
        $capturePaymentMock = function() {};
        $mollieObjectMock = $this->createConfiguredMock(MollieObject::class, [
            'cancelOrderAtMollie' => true
        ]);
        $mollieObjectMock->expects($this->once())->method('cancelOrderAtMollie')->with('123');

        MolliePluginApi::init($capturePaymentMock, $voidPaymentMock, $mollieObjectMock);
        mollie_cancel_order($mockOrder);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_shipOrder_with_correct_order() {
        $mockOrder = $this->createMock(WC_Order::class);
        $mockOrder->method('get_id')->willReturn('123');
        $voidPaymentMock = function() {};
        $capturePaymentMock = function() {};
        $mollieObjectMock = $this->createConfiguredMock(MollieObject::class, [
            'shipAndCaptureOrderAtMollie' => true
        ]);
        $mollieObjectMock->expects($this->once())->method('shipAndCaptureOrderAtMollie')->with('123');

        MolliePluginApi::init($capturePaymentMock, $voidPaymentMock, $mollieObjectMock);
        mollie_ship_order($mockOrder);
    }
}
