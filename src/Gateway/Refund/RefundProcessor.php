<?php

namespace Mollie\WooCommerce\Gateway\Refund;

use Exception;
use Mollie\Inpsyde\PaymentGateway\RefundProcessorInterface;
use Mollie\Api\Exceptions\ApiException;
use WC_Order;
class RefundProcessor implements RefundProcessorInterface
{
    private $deprecatedGatewayHelper;
    public function __construct($deprecatedGatewayHelper)
    {
        $this->deprecatedGatewayHelper = $deprecatedGatewayHelper;
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
        $payment_object_id = $this->deprecatedGatewayHelper->paymentObject()->getActiveMollieOrderId($order_id);
        // If there is no Mollie Payment Order object, try getting a Mollie Payment Payment object
        if (!$payment_object_id) {
            $payment_object_id = $this->deprecatedGatewayHelper->paymentObject()->getActiveMolliePaymentId($order_id);
        }
        // Mollie Payment object not found
        if (!$payment_object_id) {
            $error_message = __("Can\\'t process refund. Could not find Mollie Payment object id for order %s.", 'mollie-payments-for-woocommerce');
            $this->deprecatedGatewayHelper->getLogger()->debug(__METHOD__ . ' - ' . sprintf($error_message, $order_id));
            throw new Exception($error_message);
        }
        try {
            $payment_object = $this->deprecatedGatewayHelper->getPaymentFactory()->getPaymentObject($payment_object_id, $this->deprecatedGatewayHelper->paymentMethod());
        } catch (ApiException $exception) {
            $exceptionMessage = $exception->getMessage();
            $this->deprecatedGatewayHelper->getLogger()->debug($exceptionMessage);
            throw new Exception($exception->getMessage());
        }
        if (!$payment_object || !is_object($payment_object)) {
            $error_message = __("Can\\'t process refund. Could not find Mollie Payment object data for order %s.", 'mollie-payments-for-woocommerce');
            $this->deprecatedGatewayHelper->getLogger()->debug(__METHOD__ . ' - ' . sprintf($error_message, $order_id));
            throw new Exception($error_message);
        }
        $payment_object->refund($wcOrder, $order_id, $payment_object, $amount, $reason);
    }
}
