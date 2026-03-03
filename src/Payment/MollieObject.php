<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use WC_Order;
use WC_Payment_Gateway;
use Mollie\Psr\Log\LoggerInterface as Logger;
class MollieObject
{
    protected $data;
    /**
     * @var string[]
     */
    protected const FINAL_STATUSES = ['completed', 'refunded', 'canceled'];
    protected static $paymentId;
    protected static $customerId;
    protected static $order;
    protected static $payment;
    protected static $shop_country;
    /**
     * @var Logger
     */
    protected Logger $logger;
    /**
     * @var PaymentFactory
     */
    protected \Mollie\WooCommerce\Payment\PaymentFactory $paymentFactory;
    protected $dataService;
    protected $apiHelper;
    protected Settings $settingsHelper;
    protected $dataHelper;
    /**
     * @var string
     */
    protected $pluginId;
    /**
     * @var null
     */
    private $paymentMethod;
    protected RequestFactory $requestFactory;
    public function __construct($data, Logger $logger, \Mollie\WooCommerce\Payment\PaymentFactory $paymentFactory, Api $apiHelper, Settings $settingsHelper, string $pluginId, RequestFactory $requestFactory)
    {
        $this->data = $data;
        $this->logger = $logger;
        $this->paymentFactory = $paymentFactory;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->pluginId = $pluginId;
        $base_location = wc_get_base_location();
        static::$shop_country = $base_location['country'];
        $this->paymentMethod = null;
        $this->requestFactory = $requestFactory;
    }
    public function data()
    {
        return $this->data;
    }
    public function customerId()
    {
        return self::$customerId;
    }
    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $paymentId
     * @param bool   $testMode (default: false)
     * @param bool   $useCache (default: true)
     *
     * @return Payment|Order|null
     */
    public function getPaymentObject($paymentId, $testMode = \false, $useCache = \true)
    {
        return static::$payment;
    }
    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $payment_id
     * @param bool   $test_mode (default: false)
     * @param bool   $use_cache (default: true)
     *
     * @return Payment|null
     */
    public function getPaymentObjectPayment($payment_id, $test_mode = \false, $use_cache = \true)
    {
        try {
            $test_mode = $this->settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey();
            return $this->apiHelper->getApiClient($apiKey)->payments->get($payment_id);
        } catch (ApiException $apiException) {
            $this->logger->debug(__FUNCTION__ . sprintf(': Could not load payment %s (', $payment_id) . ($test_mode ? 'test' : 'live') . "): " . $apiException->getMessage() . ' (' . get_class($apiException) . ')');
        }
        return null;
    }
    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $payment_id
     * @param bool   $test_mode (default: false)
     * @param bool   $use_cache (default: true)
     *
     * @return Payment|Order|null
     */
    public function getPaymentObjectOrder($payment_id, $test_mode = \false, $use_cache = \true)
    {
        // TODO David: Duplicate, send to child class.
        try {
            // Is test mode enabled?
            $test_mode = $this->settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey();
            return $this->apiHelper->getApiClient($apiKey)->orders->get($payment_id, ["embed" => "payments"]);
        } catch (ApiException $e) {
            $this->logger->debug(__FUNCTION__ . sprintf(': Could not load order %s (', $payment_id) . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }
        return null;
    }
    /**
     * @param $order
     * @param $customerId
     *
     */
    protected function getPaymentRequestData($order, $customerId)
    {
    }
    /**
     * @param \WC_Order $order
     * @param string $new_status
     * @param string $note
     * @param bool $restore_stock
     */
    public function updateOrderStatus(\WC_Order $order, $new_status, $note = '', $restore_stock = \true)
    {
        $order->update_status($new_status, $note);
        switch ($new_status) {
            case SharedDataDictionary::STATUS_ON_HOLD:
                if ($restore_stock === \true) {
                    if (!$order->get_meta('_order_stock_reduced', \true)) {
                        // Reduce order stock
                        wc_reduce_stock_levels($order->get_id());
                        $this->logger->debug(__METHOD__ . ":  Stock for order {$order->get_id()} reduced.");
                    }
                }
                break;
            case SharedDataDictionary::STATUS_PENDING:
            case SharedDataDictionary::STATUS_FAILED:
            case SharedDataDictionary::STATUS_CANCELLED:
                if ($order->get_meta('_order_stock_reduced', \true)) {
                    // Restore order stock
                    $this->dataHelper->restoreOrderStock($order);
                    $this->logger->debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
                }
                break;
        }
    }
    /**
     * Save active Mollie payment id for order
     *
     * @param int $orderId
     *
     * @return $this
     */
    public function setActiveMolliePayment($orderId)
    {
        if ($this->dataHelper->isSubscription($orderId)) {
            return $this->setActiveMolliePaymentForSubscriptions($orderId);
        }
        return $this->setActiveMolliePaymentForOrders($orderId);
    }
    /**
     * Save active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
    public function setActiveMolliePaymentForOrders($order_id)
    {
        static::$order = wc_get_order($order_id);
        static::$order->update_meta_data('_mollie_order_id', $this->data->id);
        static::$order->set_transaction_id($this->data->id);
        static::$order->update_meta_data('_mollie_payment_id', static::$paymentId);
        static::$order->update_meta_data('_mollie_payment_mode', $this->data->mode);
        static::$order->delete_meta_data('_mollie_cancelled_payment_id');
        if (static::$customerId) {
            static::$order->update_meta_data('_mollie_customer_id', static::$customerId);
        }
        static::$order->save();
        return $this;
    }
    /**
     * Save active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
    public function setActiveMolliePaymentForSubscriptions($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_meta_data('_mollie_payment_id', static::$paymentId);
        $order->update_meta_data('_mollie_payment_mode', $this->data->mode);
        $order->delete_meta_data('_mollie_cancelled_payment_id');
        if (static::$customerId) {
            $order->update_meta_data('_mollie_customer_id', static::$customerId);
        }
        // Also store it on the subscriptions being purchased or paid for in the order
        if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin') && $this->dataHelper->isWcSubscription($order_id)) {
            if (wcs_order_contains_subscription($order_id)) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id);
            } elseif (wcs_order_contains_renewal($order_id)) {
                $subscriptions = wcs_get_subscriptions_for_renewal_order($order_id);
            } else {
                $subscriptions = [];
            }
            foreach ($subscriptions as $subscription) {
                $this->unsetActiveMolliePayment($subscription->get_id());
                $subscription->delete_meta_data('_mollie_customer_id');
                $subscription->update_meta_data('_mollie_payment_id', static::$paymentId);
                $subscription->update_meta_data('_mollie_payment_mode', $this->data->mode);
                $subscription->delete_meta_data('_mollie_cancelled_payment_id');
                if (static::$customerId) {
                    $subscription->update_meta_data('_mollie_customer_id', static::$customerId);
                }
                $subscription->save();
            }
        }
        $order->save();
        return $this;
    }
    /**
     * Delete active Mollie payment id for order
     *
     * @param int    $order_id
     * @param string $payment_id
     *
     * @return $this
     */
    public function unsetActiveMolliePayment($order_id, $payment_id = null)
    {
        if ($this->dataHelper->isSubscription($order_id)) {
            return $this->unsetActiveMolliePaymentForSubscriptions($order_id);
        }
        return $this->unsetActiveMolliePaymentForOrders($order_id);
    }
    /**
     * Delete active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
    public function unsetActiveMolliePaymentForOrders($order_id)
    {
        // Only remove Mollie payment details if they belong to this payment, not when a new payment was already placed
        $order = wc_get_order($order_id);
        $mollie_payment_id = $order->get_meta('_mollie_payment_id', \true);
        if (is_object($this->data) && isset($this->data->id) && $mollie_payment_id === $this->data->id) {
            $order->delete_meta_data('_mollie_payment_id');
            $order->delete_meta_data('_mollie_payment_mode');
            $order->save();
        }
        return $this;
    }
    /**
     * Delete active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
    public function unsetActiveMolliePaymentForSubscriptions($order_id)
    {
        $order = wc_get_order($order_id);
        $order->delete_meta_data('_mollie_payment_id');
        $order->delete_meta_data('_mollie_payment_mode');
        $order->save();
        return $this;
    }
    /**
     * Get active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return string
     */
    public function getActiveMolliePaymentId($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_meta('_mollie_payment_id', \true);
    }
    /**
     * Get active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return string
     */
    public function getActiveMollieOrderId($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_meta('_mollie_order_id', \true);
    }
    /**
     * Get active Mollie payment mode for order
     *
     * @param int $order_id
     *
     * @return string test or live
     */
    public function getActiveMolliePaymentMode($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_meta('_mollie_payment_mode', \true);
    }
    /**
     * @param int  $order_id
     * @param bool $use_cache
     *
     * @return Payment|null
     */
    public function getActiveMolliePayment($order_id, $use_cache = \true)
    {
        // Check if there is a payment ID stored with order and get it
        if ($this->hasActiveMolliePayment($order_id)) {
            return $this->getPaymentObjectPayment($this->getActiveMolliePaymentId($order_id), $this->getActiveMolliePaymentMode($order_id) === 'test', $use_cache);
        }
        // If there is no payment ID, try to get order ID and if it's stored, try getting payment ID from API
        if ($this->hasActiveMollieOrder($order_id)) {
            $mollie_order = $this->getPaymentObjectOrder($this->getActiveMollieOrderId($order_id));
            try {
                $mollie_order = $this->paymentFactory->getPaymentObject($mollie_order);
            } catch (ApiException $exception) {
                $this->logger->debug($exception->getMessage());
                return;
            }
            return $this->getPaymentObjectPayment($mollie_order->getMolliePaymentIdFromPaymentObject(), $this->getActiveMolliePaymentMode($order_id) === 'test', $use_cache);
        }
        return null;
    }
    /**
     * Check if the order has an active Mollie payment
     *
     * @param int $order_id
     *
     * @return bool
     */
    public function hasActiveMolliePayment($order_id)
    {
        $mollie_payment_id = $this->getActiveMolliePaymentId($order_id);
        return !empty($mollie_payment_id);
    }
    /**
     * Check if the order has an active Mollie order
     *
     * @param int $order_id
     *
     * @return bool
     */
    public function hasActiveMollieOrder($order_id)
    {
        $mollie_payment_id = $this->getActiveMollieOrderId($order_id);
        return !empty($mollie_payment_id);
    }
    /**
     * @param int    $order_id
     * @param string $payment_id
     *
     * @return $this
     */
    public function setCancelledMolliePaymentId($order_id, $payment_id)
    {
        $order = wc_get_order($order_id);
        $order->update_meta_data('_mollie_cancelled_payment_id', $payment_id);
        $order->save();
        return $this;
    }
    /**
     * @param int $order_id
     *
     * @return null
     */
    public function unsetCancelledMolliePaymentId($order_id)
    {
        // If this order contains a cancelled (previous) payment, remove it.
        $order = wc_get_order($order_id);
        $mollie_cancelled_payment_id = $order->get_meta('_mollie_cancelled_payment_id', \true);
        if (!empty($mollie_cancelled_payment_id)) {
            $order = wc_get_order($order_id);
            $order->delete_meta_data('_mollie_cancelled_payment_id');
            $order->save();
        }
        return null;
    }
    /**
     * @param int $order_id
     *
     * @return string|false
     */
    public function getCancelledMolliePaymentId($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_meta('_mollie_cancelled_payment_id', \true);
    }
    /**
     * Check if the order has been cancelled
     *
     * @param int $order_id
     *
     * @return bool
     */
    public function hasCancelledMolliePayment($order_id)
    {
        $cancelled_payment_id = $this->getCancelledMolliePaymentId($order_id);
        return !empty($cancelled_payment_id);
    }
    public function getMolliePaymentIdFromPaymentObject()
    {
    }
    public function getMollieCustomerIdFromPaymentObject()
    {
    }
    /**
     * @param WC_Order                     $order
     * @param Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookPaid(WC_Order $order, $payment, $paymentMethodTitle)
    {
    }
    /**
     * @param WC_Order                     $order
     * @param Payment $payment
     * @param string                       $paymentMethodTitle
     */
    protected function onWebhookCanceled(WC_Order $order, $payment, $paymentMethodTitle)
    {
    }
    /**
     * @param WC_Order                     $order
     * @param Payment $payment
     * @param string                       $paymentMethodTitle
     */
    protected function onWebhookFailed(WC_Order $order, $payment, $paymentMethodTitle)
    {
    }
    /**
     * @param WC_Order                     $order
     * @param Payment $payment
     * @param string                       $paymentMethodTitle
     */
    protected function onWebhookExpired(WC_Order $order, $payment, $paymentMethodTitle)
    {
    }
    /**
     * Process a payment object refund
     *
     * @param WC_Order $order
     * @param int    $orderId
     * @param object $paymentObject
     * @param null   $amount
     * @param string $reason
     */
    public function refund(WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '')
    {
    }
    /**
     * @return bool
     */
    protected function setOrderPaidAndProcessed(WC_Order $order)
    {
        $order->update_meta_data('_mollie_paid_and_processed', '1');
        $order->save();
        return \true;
    }
    /**
     * @return bool
     */
    protected function isOrderPaymentStartedByOtherGateway(WC_Order $order)
    {
        // Get the current payment method id for the order
        $payment_method_id = $order->get_payment_method();
        // If the current payment method id for the order is not Mollie, return true
        return strpos($payment_method_id, 'mollie') === \false;
    }
    /**
     * @param WC_Order $order
     */
    public function deleteSubscriptionFromPending(WC_Order $order)
    {
        if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin') && $this->dataHelper->isSubscription($order->get_id())) {
            $this->deleteSubscriptionOrderFromPendingPaymentQueue($order);
        }
    }
    /**
     * @param WC_Order       $order
     * @param Order| Payment $payment
     */
    protected function addMandateIdMetaToFirstPaymentSubscriptionOrder(WC_Order $order, $payment)
    {
        if ($this->dataHelper->isSubscriptionPluginActive()) {
            //get Payment from orders API
            $payment = isset($payment->_embedded->payments[0]) ? $payment->_embedded->payments[0] : $payment;
            if ($payment && (isset($payment->sequenceType) && $payment->sequenceType === 'first') && !empty($payment->mandateId)) {
                $order->update_meta_data('_mollie_mandate_id', $payment->mandateId);
                $order->save();
                $customerId = $this->getUserMollieCustomerId($order);
                do_action($this->pluginId . '_after_mandate_created', $payment, $order, $customerId, $payment->mandateId);
                $subscriptions = wcs_get_subscriptions_for_order($order);
                if (!$subscriptions) {
                    $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
                }
                foreach ($subscriptions as $subscription) {
                    $subscription->update_meta_data('_mollie_payment_id', $payment->id);
                    $subscription->update_meta_data('_mollie_mandate_id', $payment->mandateId);
                    $subscription->set_payment_method('mollie_wc_gateway_' . $payment->method);
                    $subscription->save();
                    $subscriptionParentOrder = $subscription->get_parent();
                    if ($subscriptionParentOrder) {
                        $subscriptionParentOrder->update_meta_data('_mollie_mandate_id', $payment->mandateId);
                        $subscriptionParentOrder->save();
                    }
                }
            }
        }
    }
    /**
     * @param $order
     * @param $test_mode
     * @return null|string
     */
    protected function getUserMollieCustomerId($order)
    {
        $order_customer_id = $order->get_customer_id();
        $apiKey = $this->settingsHelper->getApiKey();
        return $this->dataHelper->getUserMollieCustomerId($order_customer_id, $apiKey);
    }
    /**
     * @param $order
     */
    public function deleteSubscriptionOrderFromPendingPaymentQueue($order)
    {
        global $wpdb;
        $wpdb->delete($wpdb->mollie_pending_payment, ['post_id' => $order->get_id()]);
    }
    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function isFinalOrderStatus(WC_Order $order)
    {
        $orderStatus = $order->get_status();
        return in_array($orderStatus, self::FINAL_STATUSES, \true);
    }
    /**
     * @param int                           $orderId
     * @param \WC_Payment_Gateway           $gateway
     * @param \WC_Order                     $order
     * @param string                        $newOrderStatus
     * @param string                        $paymentMethodTitle
     * @param Payment|Order                 $payment
     */
    protected function failedSubscriptionProcess($orderId, WC_Payment_Gateway $gateway, WC_Order $order, $newOrderStatus, $paymentMethodTitle, $payment)
    {
        $paymentID = $payment->id . ($payment->mode === 'test' ? ' - ' . __('test mode', 'mollie-payments-for-woocommerce') : '');
        $orderNote = sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%1$s payment failed via Mollie (%2$s)', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $paymentID
        );
        //check if there is a reason for failed payment and print it to order note
        $failureReason = $payment->details->failureReason ?? '';
        if ($failureReason) {
            $orderNote = sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID, placeholder 3: failure reason, placeholder 4: failure message */
                __('%1$s payment failed via Mollie (%2$s). Because of: (%3$s) %4$s.', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $paymentID,
                $failureReason,
                $payment->details->failureMessage ?? ''
            );
        }
        if (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($orderId)) {
            add_filter('wcs_is_scheduled_payment_attempt', '__return_true');
            $this->updateOrderStatus($order, $newOrderStatus, sprintf(__('Renewal: %s', 'mollie-payments-for-woocommerce'), $orderNote), \false);
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', renewal order payment failed, order set to ' . $newOrderStatus . ' for shop-owner review.');
            // Send a "Failed order" email to notify the admin
            $emails = WC()->mailer()->get_emails();
            if (!empty($emails) && !empty($orderId) && !empty($emails['WC_Email_Failed_Order'])) {
                $emails['WC_Email_Failed_Order']->trigger($orderId);
            }
        } elseif (mollieWooCommerceIsMollieGateway($gateway->id)) {
            $this->updateOrderStatus($order, $newOrderStatus, $orderNote);
        }
    }
    /**
     * @param string $gatewayId
     * @param WC_Order $order
     */
    protected function informNotUpdatingStatus($gatewayId, WC_Order $order)
    {
        $orderPaymentMethodTitle = $order->get_meta('_payment_method_title');
        // Add message to log
        $this->logger->debug($gatewayId . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $orderPaymentMethodTitle . ', so order status not updated.', [\true]);
        // Add order note
        $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Mollie webhook called, but payment also started via %s, so the order status is not updated.', 'mollie-payments-for-woocommerce'),
            $orderPaymentMethodTitle
        ));
    }
    protected function addPaypalTransactionIdToOrder(WC_Order $order)
    {
        $payment = $this->getActiveMolliePayment($order->get_id());
        if ($payment->isPaid() && $payment->details) {
            $order->add_meta_data('_paypal_transaction_id', $payment->details->paypalReference);
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                __("Payment completed by <strong>%1\$s</strong> - %2\$s (PayPal transaction ID: %3\$s)", 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                $payment->details->consumerAccount,
                $payment->details->paypalReference
            ));
            $order->save();
        }
    }
}
