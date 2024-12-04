<?php

namespace Mollie\WooCommerce\Gateway\Refund;

use Exception;
use Inpsyde\PaymentGateway\RefundProcessorInterface;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayI;
use WC_Order;

class RefundProcessor implements RefundProcessorInterface
{
    private MolliePaymentGatewayI $molliePaymentGateway;

    public function __construct(MolliePaymentGatewayI $molliePaymentGateway)
    {
        $this->molliePaymentGateway = $molliePaymentGateway;
    }

    /**
     * Process a refund if supported
     *
     * @param WC_Order $wcOrder
     * @param float $amount
     * @param string $reason
     *
     * @return void
     * @throws Exception
     * @since WooCommerce 2.2
     */
    public function refundOrderPayment(WC_Order $wcOrder, $amount = null, $reason = ''): void
    {
        $order_id = $wcOrder->get_id();

        // Check if there is a Mollie Payment Order object connected to this WooCommerce order
        $payment_object_id = $this->molliePaymentGateway->paymentObject()->getActiveMollieOrderId(
            $order_id
        );

        // If there is no Mollie Payment Order object, try getting a Mollie Payment Payment object
        if (!$payment_object_id) {
            $payment_object_id = $this->molliePaymentGateway->paymentObject()
                ->getActiveMolliePaymentId($order_id);
        }

        // Mollie Payment object not found
        if (!$payment_object_id) {
            $error_message = __("Can\'t process refund. Could not find Mollie Payment object id for order $order_id.", 'mollie-payments-for-woocommerce');

            $this->molliePaymentGateway->getLogger()->debug(
                __METHOD__ . ' - ' . $error_message
            );

            throw new Exception($error_message);
        }

        try {
            $payment_object = $this->molliePaymentGateway->getPaymentFactory()
                ->getPaymentObject(
                    $payment_object_id,
                    $this->molliePaymentGateway->paymentMethod()
                );
        } catch (ApiException $exception) {
            $exceptionMessage = $exception->getMessage();
            $this->molliePaymentGateway->getLogger()->debug($exceptionMessage);
            throw new Exception($exception->getMessage());
        }

        if (!$payment_object || !is_object($payment_object)) {
            $error_message = __("Can\'t process refund. Could not find Mollie Payment object data for order $order_id.", 'mollie-payments-for-woocommerce');
            $this->molliePaymentGateway->getLogger()->debug(
                __METHOD__ . ' - ' . $error_message
            );

            throw new Exception($error_message);
        }

        $payment_object->refund(
            $wcOrder,
            $order_id,
            $payment_object,
            $amount,
            $reason
        );
    }
}
