<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Webhooks;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\Psr\Log\LogLevel;
use WC_Order;
class WebhookHandler
{
    /**
     * @var Logger
     */
    private Logger $logger;
    /**
     * @var Settings
     */
    private Settings $settingsHelper;
    /**
     * @var string
     */
    private string $pluginId;
    /**
     * @var Data
     */
    private Data $dataHelper;
    public function __construct(Logger $logger, Settings $settingsHelper, string $pluginId, Data $dataHelper)
    {
        $this->logger = $logger;
        $this->settingsHelper = $settingsHelper;
        $this->pluginId = $pluginId;
        $this->dataHelper = $dataHelper;
    }
    /**
     * @param WC_Order $order
     * @param Payment|Order $payment
     * @param string $paymentMethodTitle
     * @param MollieObject $mollieObject
     */
    public function onWebhookPaid(WC_Order $order, $payment, string $paymentMethodTitle, MollieObject $mollieObject): void
    {
        $orderId = $order->get_id();
        if (!$payment->isPaid()) {
            $this->logger->debug(__METHOD__ . " payment at Mollie not paid, so no processing for order {$orderId}");
            return;
        }
        $this->logger->debug(__METHOD__ . " called for order {$orderId}");
        if ($payment->method === 'paypal') {
            $mollieObject->addPaypalTransactionIdToOrder($order);
        }
        if (!empty($payment->amountChargedBack)) {
            $this->logger->debug(__METHOD__ . ' payment at Mollie has a chargeback, so no processing for order ' . $orderId);
            return;
        }
        $order->payment_complete($payment->id);
        $this->logger->debug(__METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . " for order {$orderId}");
        $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Order completed using %1$s payment (%2$s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $this->formatPaymentId($payment)
        ));
        $mollieObject->setOrderPaidAndProcessed($order);
        $mollieObject->unsetCancelledMolliePaymentId($orderId);
        $this->logger->debug(__METHOD__ . " processing paid order via Mollie plugin fully completed for order {$orderId}");
        if ($mollieObject instanceof MollieOrder) {
            $this->handleOrderPaidSpecifics($order, $payment, $mollieObject, $orderId);
        } elseif ($mollieObject instanceof MolliePayment) {
            $this->handlePaymentPaidSpecifics($order, $payment, $mollieObject, $orderId);
        }
    }
    /**
     * @param WC_Order $order
     * @param Payment|Order $payment
     * @param string $paymentMethodTitle
     * @param MollieObject $mollieObject
     */
    public function onWebhookAuthorized(WC_Order $order, $payment, string $paymentMethodTitle, MollieObject $mollieObject): void
    {
        $orderId = $order->get_id();
        if ($order->get_meta('_mollie_authorized') === '1') {
            $this->logger->debug(__METHOD__ . " order {$orderId} is already authorized.");
            return;
        }
        if (!$payment->isAuthorized()) {
            $this->logger->debug(__METHOD__ . ' order at Mollie not authorized, so no processing for order ' . $orderId);
            return;
        }
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);
        $order->payment_complete($payment->id);
        $this->logger->debug(__METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $orderId);
        $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Order authorized using %1$s payment (%2$s). Set order to completed in WooCommerce when you have shipped the products, to capture the payment. Do this within 28 days, or the order will expire. To handle individual order lines, process the order via the Mollie Dashboard.', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $this->formatPaymentId($payment)
        ));
        $order->update_meta_data('_mollie_authorized', '1');
        $mollieObject->setOrderPaidAndProcessed($order);
        $mollieObject->unsetCancelledMolliePaymentId($orderId);
        $this->logger->debug(__METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $orderId);
        $mollieObject->deleteSubscriptionFromPending($order);
    }
    /**
     * Only applicable for Orders API (MollieOrder context).
     *
     * @param WC_Order $order
     * @param Order $payment
     * @param string $paymentMethodTitle
     * @param MollieObject $mollieObject
     */
    public function onWebhookCompleted(WC_Order $order, $payment, string $paymentMethodTitle, MollieObject $mollieObject): void
    {
        $orderId = $order->get_id();
        if (!$payment->isCompleted()) {
            $this->logger->debug(__METHOD__ . ' order at Mollie not completed, so no further processing for order ' . $orderId);
            return;
        }
        if ($mollieObject instanceof MolliePayment) {
            return;
        }
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);
        if ($payment->method === 'paypal') {
            $mollieObject->addPaypalTransactionIdToOrder($order);
        }
        add_filter('woocommerce_valid_order_statuses_for_payment_complete', static function ($statuses) {
            $statuses[] = 'processing';
            return $statuses;
        });
        add_filter('woocommerce_payment_complete_order_status', static function ($status) use ($order) {
            return $order->get_status() === 'processing' ? 'completed' : $status;
        });
        $order->payment_complete($payment->id);
        $this->logger->debug(__METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $orderId);
        $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Order completed at Mollie for %1$s order (%2$s). At least one order line completed. Remember: Completed status for an order at Mollie is not the same as Completed status in WooCommerce!', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $this->formatPaymentId($payment)
        ));
        $mollieObject->setOrderPaidAndProcessed($order);
        $mollieObject->unsetCancelledMolliePaymentId($orderId);
        $this->logger->debug(__METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $orderId);
        $mollieObject->deleteSubscriptionFromPending($order);
    }
    /**
     * @param WC_Order $order
     * @param Payment|Order $payment
     * @param string $paymentMethodTitle
     * @param MollieObject $mollieObject
     */
    public function onWebhookCanceled(WC_Order $order, $payment, string $paymentMethodTitle, MollieObject $mollieObject): void
    {
        $orderId = $order->get_id();
        $this->logger->debug(__METHOD__ . " called for order {$orderId}");
        if ($mollieObject->isFinalOrderStatus($order)) {
            $this->logger->debug(__METHOD__ . " called for payment {$orderId} has final status. Nothing to be done");
            return;
        }
        $mollieObject->unsetActiveMolliePayment($orderId, $payment->id);
        $mollieObject->setCancelledMolliePaymentId($orderId, $payment->id);
        $orderStatusCancelledPayments = $this->settingsHelper->getOrderStatusCancelledPayments();
        if ($orderStatusCancelledPayments === 'pending' || $orderStatusCancelledPayments === null) {
            $newOrderStatus = SharedDataDictionary::STATUS_PENDING;
        } elseif ($orderStatusCancelledPayments === 'cancelled') {
            $newOrderStatus = SharedDataDictionary::STATUS_CANCELLED;
        }
        if ($order->get_status() === 'cancelled') {
            $newOrderStatus = SharedDataDictionary::STATUS_CANCELLED;
        }
        $gateway = wc_get_payment_gateway_by_order($order);
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_cancelled', $newOrderStatus);
        // MollieOrder uses $payment->method for the filter suffix, MolliePayment uses $gateway->id
        $filterSuffix = $mollieObject instanceof MollieOrder ? $payment->method : $gateway->id;
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_cancelled_' . $filterSuffix, $newOrderStatus);
        $this->maybeUpdateStatus($order, $gateway, $newOrderStatus, $mollieObject);
        // Preserve exact translation strings per context for i18n compatibility
        if ($mollieObject instanceof MollieOrder) {
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s order (%2$s) cancelled .', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $this->formatPaymentId($payment)
            ));
        } else {
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s payment (%2$s) cancelled .', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $this->formatPaymentId($payment)
            ));
        }
        $mollieObject->deleteSubscriptionFromPending($order);
    }
    /**
     * @param WC_Order $order
     * @param Payment|Order $payment
     * @param string $paymentMethodTitle
     * @param MollieObject $mollieObject
     */
    public function onWebhookFailed(WC_Order $order, $payment, string $paymentMethodTitle, MollieObject $mollieObject): void
    {
        $orderId = $order->get_id();
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);
        $gateway = wc_get_payment_gateway_by_order($order);
        $newOrderStatus = SharedDataDictionary::STATUS_FAILED;
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed', $newOrderStatus);
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed_' . $gateway->id, $newOrderStatus);
        $mollieObject->failedSubscriptionProcess($orderId, $gateway, $order, $newOrderStatus, $paymentMethodTitle, $payment);
        if (isset($payment->details->failureReason)) {
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', regular payment failed because of ' . esc_attr($payment->details->failureReason) . '.');
        } else {
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', regular payment failed.');
        }
    }
    /**
     * @param WC_Order $order
     * @param Payment|Order $payment
     * @param string $paymentMethodTitle
     * @param MollieObject $mollieObject
     */
    public function onWebhookExpired(WC_Order $order, $payment, string $paymentMethodTitle, MollieObject $mollieObject): void
    {
        $orderId = $order->get_id();
        $metaKey = $mollieObject instanceof MollieOrder ? '_mollie_order_id' : '_mollie_payment_id';
        $molliePaymentId = $order->get_meta($metaKey, \true);
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);
        if (!$order->needs_payment()) {
            $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' called for order ' . $orderId . ', not processed because the order is already paid.');
            return;
        }
        if ($molliePaymentId !== $payment->id) {
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $molliePaymentId);
            $formattedId = $this->formatPaymentId($payment);
            // Preserve exact translation strings per context for i18n compatibility
            if ($mollieObject instanceof MollieOrder) {
                $order->add_order_note(sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID, placeholder 3: newer payment ID */
                    __('%1$s order expired (%2$s) but not cancelled because of another pending payment (%3$s).', 'mollie-payments-for-woocommerce'),
                    $paymentMethodTitle,
                    $formattedId,
                    $molliePaymentId
                ));
            } else {
                $order->add_order_note(sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID, placeholder 3: newer payment ID */
                    __('%1$s payment expired (%2$s) but not cancelled because of another pending payment (%3$s).', 'mollie-payments-for-woocommerce'),
                    $paymentMethodTitle,
                    $formattedId,
                    $molliePaymentId
                ));
            }
            return;
        }
        $newOrderStatus = SharedDataDictionary::STATUS_CANCELLED;
        $gateway = wc_get_payment_gateway_by_order($order);
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_expired', $newOrderStatus);
        // MollieOrder uses $payment->method for the filter suffix, MolliePayment uses $gateway->id
        $filterSuffix = $mollieObject instanceof MollieOrder ? $payment->method : $gateway->id;
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_expired_' . $filterSuffix, $newOrderStatus);
        $this->maybeUpdateStatus($order, $gateway, $newOrderStatus, $mollieObject);
        // Preserve exact translation strings per context for i18n compatibility
        if ($mollieObject instanceof MollieOrder) {
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s order (%2$s) expired .', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $this->formatPaymentId($payment)
            ));
        } else {
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s payment expired (%2$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $this->formatPaymentId($payment)
            ));
        }
        $mollieObject->unsetCancelledMolliePaymentId($orderId);
        // Only MollieOrder deletes subscription from pending on expiry
        if ($mollieObject instanceof MollieOrder) {
            $mollieObject->deleteSubscriptionFromPending($order);
        }
    }
    public function onWebhookPending(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        $this->logger->debug(__METHOD__ . " called for order {$orderId}");
        //if order is paid or completed, do not process this webhook
        if ($order->is_paid() || $order->get_status() === 'completed') {
            return;
        }
        $order->add_order_note(sprintf(__('%1$s payment pending (%2$s).', 'mollie-payments-for-woocommerce'), $paymentMethodTitle, $this->formatPaymentId($payment)));
    }
    /**
     * Handle MollieOrder-specific logic after a paid webhook.
     *
     * @param WC_Order $order
     * @param Order $payment
     * @param MollieOrder $mollieObject
     * @param int $orderId
     */
    private function handleOrderPaidSpecifics(WC_Order $order, $payment, MollieOrder $mollieObject, int $orderId): void
    {
        $mollieObject->updatePaymentDataWithOrderData($payment, $orderId);
        $this->logger->debug(__METHOD__ . ' updated payment with webhook and metadata');
        $mollieObject->addMandateIdMetaToFirstPaymentSubscriptionOrder($order, $payment);
        $mollieObject->deleteSubscriptionFromPending($order);
    }
    /**
     * Handle MolliePayment-specific logic after a paid webhook.
     *
     * @param WC_Order $order
     * @param Payment $payment
     * @param MolliePayment $mollieObject
     * @param int $orderId
     */
    private function handlePaymentPaidSpecifics(WC_Order $order, $payment, MolliePayment $mollieObject, int $orderId): void
    {
        $mollieObject->addMandateIdMetaToFirstPaymentSubscriptionOrder($order, $payment);
        if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin')) {
            if ($this->dataHelper->isWcSubscription($orderId)) {
                $mollieObject->deleteSubscriptionOrderFromPendingPaymentQueue($order);
                \WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
            }
        }
    }
    /**
     * Update order status only if payment was not started by another gateway.
     * Delegates to MollieObject::updateOrderStatus to preserve stock handling logic.
     *
     * @param WC_Order $order
     * @param mixed $gateway
     * @param string $newOrderStatus
     * @param MollieObject $mollieObject
     */
    private function maybeUpdateStatus(WC_Order $order, $gateway, string $newOrderStatus, MollieObject $mollieObject): void
    {
        if ($this->isOrderPaymentStartedByOtherGateway($order) || !is_a($gateway, PaymentGateway::class)) {
            $this->informNotUpdatingStatus($gateway->id ?? '', $order);
            return;
        }
        $mollieObject->updateOrderStatus($order, $newOrderStatus);
    }
    /**
     * @param WC_Order $order
     * @return bool
     */
    private function isOrderPaymentStartedByOtherGateway(WC_Order $order): bool
    {
        $paymentMethodId = $order->get_payment_method();
        return strpos($paymentMethodId, 'mollie') === \false;
    }
    /**
     * @param string $gatewayId
     * @param WC_Order $order
     */
    private function informNotUpdatingStatus(string $gatewayId, WC_Order $order): void
    {
        $orderPaymentMethodTitle = $order->get_meta('_payment_method_title');
        $this->logger->debug($gatewayId . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $orderPaymentMethodTitle . ', so order status not updated.', [\true]);
        $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title */
            __('Mollie webhook called, but payment also started via %s, so the order status is not updated.', 'mollie-payments-for-woocommerce'),
            $orderPaymentMethodTitle
        ));
    }
    /**
     * @param Payment|Order $payment
     * @return string
     */
    private function formatPaymentId($payment): string
    {
        return $payment->id . ($payment->mode === 'test' ? ' - ' . __('test mode', 'mollie-payments-for-woocommerce') : '');
    }
}
