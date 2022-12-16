<?php

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerceTests\TestCase;


use function Brain\Monkey\Functions\expect;

class MollieOrderTest extends TestCase
{
    /**
     * Test MollieOrder::onWebhookPaid
     * updates order status
     * adds order note
     * updates order and payment meta
     *
     * @test
     */
    public function onWebhookPaidUpdatesOrder() {
        $this->bootsrapMolliePlugin();
        $package = $this->createPackage($this->properties, $this->modules);
        $order = $this->createWCOrder();
        $paymentMethodTitle = 'mollie_wc_gateway_ideal';
        $mollieOrder = $this->createMollieOrder($package);
        $payment = $this->createMollieResourcePayment();
        $payment->expects($this->once())->method('isPaid')->willReturn(true);
        $paymentId = 'tr_123';
        $payment->id = $paymentId;
        $order->expects($this->once())->method('payment_complete')->with($paymentId);
        $order->expects($this->once())->method('add_order_note')->with('Order completed using '. $paymentMethodTitle .' payment ('.$paymentId.').');
        expect('wc_get_order')->with($order->get_id())->andReturn($order);
        $order->expects($this->once())->method('update_meta_data')->with('_mollie_paid_and_processed', '1');
        $payment->expects($this->once())->method('payments')->willReturn([$payment]);
        $payment->expects($this->once())->method('update');

        $mollieOrder->onWebhookPaid($order, $payment, $paymentMethodTitle);
    }

    /**
     * Test MollieOrder::onWebhookAuthorized
     * updates order status
     * adds order note
     * updates order
     *
     * @test
     */
    public function onWebhookAuthorizedUpdatesOrder() {
        $this->bootsrapMolliePlugin();
        $package = $this->createPackage($this->properties, $this->modules);
        $order = $this->createWCOrder();
        $paymentMethodTitle = 'mollie_wc_gateway_ideal';
        $mollieOrder = $this->createMollieOrder($package);
        $payment = $this->createMollieResourcePayment();
        $payment->expects($this->once())->method('isAuthorized')->willReturn(true);
        $paymentId = 'tr_123';
        $payment->id = $paymentId;
        $order->expects($this->once())->method('payment_complete')->with($paymentId);
        $order->expects($this->once())->method('add_order_note')->with(
            'Order authorized using ' . $paymentMethodTitle . ' payment (' . $paymentId . '). Set order to completed in WooCommerce when you have shipped the products, to capture the payment. Do this within 28 days, or the order will expire. To handle individual order lines, process the order via the Mollie Dashboard.'
        );
        $order->expects($this->once())->method('update_meta_data')->with('_mollie_paid_and_processed', '1');
        expect('wc_get_order')->with($order->get_id())->andReturn($order);

        $mollieOrder->onWebhookAuthorized($order, $payment, $paymentMethodTitle);
    }

    /**
     * Test MollieOrder::onWebhookCompleted
     * updates order status
     * adds order note
     * updates order
     *
     * @test
     */
    public function onWebhookCompletedUpdatesOrder() {
        $this->bootsrapMolliePlugin();
        $package = $this->createPackage($this->properties, $this->modules);
        $order = $this->createWCOrder();
        $paymentMethodTitle = 'mollie_wc_gateway_ideal';
        $mollieOrder = $this->createMollieOrder($package);
        $payment = $this->createMollieResourcePayment();
        $payment->expects($this->once())->method('isCompleted')->willReturn(true);
        $paymentId = 'tr_123';
        $payment->id = $paymentId;
        $order->expects($this->once())->method('payment_complete')->with($paymentId);
        $order->expects($this->once())->method('add_order_note')->with(
            'Order completed at Mollie for ' . $paymentMethodTitle . ' order (' . $paymentId . '). At least one order line completed. Remember: Completed status for an order at Mollie is not the same as Completed status in WooCommerce!'
        );
        $order->expects($this->once())->method('update_meta_data')->with('_mollie_paid_and_processed', '1');
        expect('wc_get_order')->with($order->get_id())->andReturn($order);

        $mollieOrder->onWebhookCompleted($order, $payment, $paymentMethodTitle);
    }

    /**
     * Test MollieOrder::onWebhookCanceled
     * updates order status
     * adds order note
     * updates order
     *
     * @test
     */
    public function onWebhookCanceledUpdates_NOTFINAL_Order() {
        $this->bootsrapMolliePlugin();
        $package = $this->createPackage($this->properties, $this->modules);
        $order = $this->createWCOrder();
        $paymentMethodTitle = 'mollie_wc_gateway_ideal';
        $mollieOrder = $this->createMollieOrder($package);
        $payment = $this->createMollieResourcePayment();
        $paymentId = 'tr_123';
        $payment->id = $paymentId;
        $order->expects($this->exactly(2))->method('get_status')->willReturn('on-hold');
        expect('wc_get_order')->with($order->get_id())->andReturn($order);

        $order->expects($this->once())->method('get_meta')->with('_mollie_payment_id')->willReturn($paymentId);
        $order->expects($this->once())->method('delete_meta_data')->with('_mollie_payment_id');
        $order->expects($this->once())->method('update_meta_data')->with('_mollie_cancelled_payment_id', $paymentId);
        $gateway = $this->createMollieGateway();
        expect('wc_get_payment_gateway_by_order')->with($order)->andReturn($gateway);
        expect('get_post_meta')->with($order->get_id(), '_payment_method', true)->andReturn($paymentMethodTitle);
        $gatewayPaymentService = $this->createPaymentService();
        $gateway->expects($this->once())->method('paymentService')->willReturn($gatewayPaymentService);
        $gatewayPaymentService->expects($this->once())->method('updateOrderStatus')->with($order, 'pending');
        $order->expects($this->once())->method('update_status')->with('cancelled');
        $order->expects($this->exactly(2))->method('add_order_note')->withConsecutive(
                [$paymentMethodTitle . ' order (' . $paymentId . ') expired .'],
                [$paymentMethodTitle . ' order (' . $paymentId . ') cancelled .']
        );
        $mollieOrder->onWebhookCanceled($order, $payment, $paymentMethodTitle);
    }

    /**
     * Test MollieOrder::onWebhookFailed
     * updates order status
     * adds order note
     *
     * @test
     */
    public function onWebhookFailedUpdatesOrder() {
        $this->bootsrapMolliePlugin();
        $package = $this->createPackage($this->properties, $this->modules);
        $order = $this->createWCOrder();
        $paymentMethodTitle = 'mollie_wc_gateway_ideal';
        $mollieOrder = $this->createMollieOrder($package);
        $payment = $this->createMollieResourcePayment();
        $paymentId = 'tr_123';
        $payment->id = $paymentId;
        $gateway = $this->createMollieGateway();
        expect('wc_get_payment_gateway_by_order')->with($order)->andReturn($gateway);
        $gatewayPaymentService = $this->createPaymentService();
        $gateway->expects($this->once())->method('paymentService')->willReturn($gatewayPaymentService);
        $gatewayPaymentService->expects($this->once())->method('updateOrderStatus')->with(
            $order,
            'failed',
            $paymentMethodTitle . ' payment failed via Mollie (' . $payment->id . ').'
        );

        $mollieOrder->onWebhookFailed($order, $payment, $paymentMethodTitle);
    }

    /**
     * Test MollieOrder::onWebhookExpired
     * updates order status
     *
     * @test
     */
    public function onWebhookExpiredUpdatesOrder() {
        $this->bootsrapMolliePlugin();
        $package = $this->createPackage($this->properties, $this->modules);
        $order = $this->createWCOrder();
        $paymentMethodTitle = 'mollie_wc_gateway_ideal';
        $mollieOrder = $this->createMollieOrder($package);
        $payment = $this->createMollieResourcePayment();
        $paymentId = 'tr_123';
        $payment->id = $paymentId;
        $order->expects($this->exactly(2))->method('get_meta')->withConsecutive(['_mollie_order_id', true], ['_mollie_cancelled_payment_id', true])->willReturnOnConsecutiveCalls($paymentId, null);
        $order->expects($this->once())->method('needs_payment')->willReturn('true');
        $gateway = $this->createMollieGateway();
        expect('wc_get_payment_gateway_by_order')->with($order)->andReturn($gateway);
        expect('get_post_meta')->with($order->get_id(), '_payment_method', true)->andReturn($paymentMethodTitle);
        $gatewayPaymentService = $this->createPaymentService();
        $gateway->expects($this->once())->method('paymentService')->willReturn($gatewayPaymentService);
        $gatewayPaymentService->expects($this->once())->method('updateOrderStatus')->with(
            $order,
            'cancelled'
        );
        expect('wc_get_order')->with($order->get_id())->andReturn($order);

        $mollieOrder->onWebhookExpired($order, $payment, $paymentMethodTitle);
    }

    private function createWCOrder($id='1', $orderKey='order-key')
    {
        return $this->woocommerceMocks->wcOrder($id, $orderKey);
    }

    private function createMollieOrder(\Inpsyde\Modularity\Package $package)
    {
        $container = $package->container();
        return $container->get(MollieOrder::class);
    }

    private function createMollieResourcePayment()
    {
        return $this->getMockBuilder(\Mollie\Api\Resources\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createMollieGateway()
    {
        return $this->getMockBuilder(MolliePaymentGateway::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createPaymentService()
    {
        return $this->getMockBuilder(PaymentService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
