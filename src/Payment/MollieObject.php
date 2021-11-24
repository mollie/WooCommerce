<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Utils\Data;
use WC_Order;
use WC_Payment_Gateway;
use Psr\Log\LoggerInterface as Logger;

class MollieObject
{

    public $data;
    /**
     * @var string[]
     */
    const FINAL_STATUSES = ['completed', 'refunded', 'canceled'];

    public static $paymentId;
    public static $customerId;
    public static $order;
    public static $payment;
    public static $shop_country;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    protected $dataService;
    protected $apiHelper;
    protected $settingsHelper;
    protected $dataHelper;

    public function __construct($data, Logger $logger, PaymentFactory $paymentFactory, Api $apiHelper, Settings $settingsHelper)
    {
        $this->data = $data;
        $this->logger = $logger;
        $this->paymentFactory = $paymentFactory;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;

        $base_location = wc_get_base_location();
        static::$shop_country = $base_location['country'];
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
    public function getPaymentObject($paymentId, $testMode = false, $useCache = true)
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
    public function getPaymentObjectPayment($payment_id, $test_mode = false, $use_cache = true)
    {
        try {
            $test_mode = $this->settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey($test_mode);
            return $this->apiHelper->getApiClient($apiKey)->payments->get($payment_id);
        } catch (ApiException $apiException) {
            $this->logger->log(\WC_Log_Levels::DEBUG, __FUNCTION__ . sprintf(': Could not load payment %s (', $payment_id) . ( $test_mode ? 'test' : 'live' ) . "): " . $apiException->getMessage() . ' (' . get_class($apiException) . ')');
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
    public function getPaymentObjectOrder($payment_id, $test_mode = false, $use_cache = true)
    {
        // TODO David: Duplicate, send to child class.
        try {
            // Is test mode enabled?
            $test_mode = $this->settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey($test_mode);
            return $this->apiHelper->getApiClient($apiKey)->orders->get($payment_id, [ "embed" => "payments" ]);
        } catch (ApiException $e) {
            $this->logger->log(\WC_Log_Levels::DEBUG, __FUNCTION__ . sprintf(': Could not load order %s (', $payment_id) . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
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
     * Save active Mollie payment id for order
     *
     * @param int $orderId
     *
     * @return $this
     */
    public function setActiveMolliePayment($orderId)
    {
        // Do extra checks if WooCommerce Subscriptions is installed
        if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin') && $this->dataHelper->isWcSubscription($orderId)) {
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
        if (wcs_order_contains_subscription($order_id)) {
            $subscriptions = wcs_get_subscriptions_for_order($order_id);
        } elseif (wcs_order_contains_renewal($order_id)) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order_id);
        } else {
            $subscriptions =  [];
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
        // Do extra checks if WooCommerce Subscriptions is installed
        if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin') && $this->dataHelper->isWcSubscription($order_id)) {
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
        $mollie_payment_id = $order->get_meta('_mollie_payment_id', true);

        if (is_object($this->data) && isset($this->data->id) && $mollie_payment_id == $this->data->id ) {
            $order->delete_meta_data( '_mollie_payment_id' );
            $order->delete_meta_data( '_mollie_payment_mode' );
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
        return $order->get_meta('_mollie_payment_id', true);
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
        return $order->get_meta('_mollie_order_id', true);
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
        return $order->get_meta('_mollie_payment_mode', true);
    }

    /**
     * @param int  $order_id
     * @param bool $use_cache
     *
     * @return Payment|null
     */
    public function getActiveMolliePayment($order_id, $use_cache = true)
    {
        // Check if there is a payment ID stored with order and get it
        if ($this->hasActiveMolliePayment($order_id)) {
            return $this->getPaymentObjectPayment(
                $this->getActiveMolliePaymentId($order_id),
                $this->getActiveMolliePaymentMode($order_id) == 'test',
                $use_cache
            );
        }

        // If there is no payment ID, try to get order ID and if it's stored, try getting payment ID from API
        if ($this->hasActiveMollieOrder($order_id)) {
            $mollie_order = $this->getPaymentObjectOrder($this->getActiveMollieOrderId($order_id));

            try {
                $mollie_order = $this->paymentFactory->getPaymentObject(
                    $mollie_order
                );
            } catch (ApiException $exception) {
                $this->logger->log(\WC_Log_Levels::DEBUG, $exception->getMessage());
                return;
            }

            return $this->getPaymentObjectPayment(
                $mollie_order->getMolliePaymentIdFromPaymentObject(),
                $this->getActiveMolliePaymentMode($order_id) == 'test',
                $use_cache
            );
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

        return ! empty($mollie_payment_id);
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

        return ! empty($mollie_payment_id);
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
        $mollie_cancelled_payment_id = $order->get_meta('_mollie_cancelled_payment_id', true);

        if (! empty($mollie_cancelled_payment_id)) {
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
        return $order->get_meta('_mollie_cancelled_payment_id', true);
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

        return ! empty($cancelled_payment_id);
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
     * @param object $order
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

        return true;
    }

    /**
     * @return bool
     */
    protected function isOrderPaymentStartedByOtherGateway(WC_Order $order)
    {
        $order_id = $order->get_id();
        // Get the current payment method id for the order
        $payment_method_id = get_post_meta($order_id, '_payment_method', $single = true);
        // If the current payment method id for the order is not Mollie, return true
        return strpos($payment_method_id, 'mollie') === false;
    }
    /**
     * @param WC_Order $order
     */
    public function deleteSubscriptionFromPending(WC_Order $order)
    {
        if (class_exists('WC_Subscriptions')
            && class_exists(
                'WC_Subscriptions_Admin'
            ) && $this->dataHelper->isSubscription(
            $order->get_id()
        )
        ) {
            $this->deleteSubscriptionOrderFromPendingPaymentQueue($order);
        }
    }

    /**
     * @param WC_Order       $order
     * @param Order| Payment $payment
     */
    protected function addMandateIdMetaToFirstPaymentSubscriptionOrder(
        WC_Order $order,
        $payment
    ) {

        if (class_exists('WC_Subscriptions')) {
            $payment = isset($payment->_embedded->payments[0])? $payment->_embedded->payments[0] : false;
            if ($payment && $payment->sequenceType === 'first'
                && (property_exists($payment, 'mandateId') && $payment->mandateId !== null)) {
                $order->update_meta_data(
                    '_mollie_mandate_id',
                    $payment->mandateId
                );
                $order->save();
            }
        }
    }

    /**
     * @param $order
     */
    public function deleteSubscriptionOrderFromPendingPaymentQueue($order)
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->mollie_pending_payment,
            [
                'post_id' => $order->get_id(),
            ]
        );
    }

    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function isFinalOrderStatus(WC_Order $order)
    {
        $orderStatus = $order->get_status();

        return in_array(
            $orderStatus,
            self::FINAL_STATUSES,
            true
        );
    }
    /**
     * @param                               $orderId
     * @param WC_Payment_Gateway            $gateway
     * @param WC_Order                      $order
     * @param                               $newOrderStatus
     * @param                               $paymentMethodTitle
     * @param Payment|Order $payment
     */
    protected function failedSubscriptionProcess(
        $orderId,
        WC_Payment_Gateway $gateway,
        WC_Order $order,
        $newOrderStatus,
        $paymentMethodTitle,
        $payment
    ) {

        if (function_exists('wcs_order_contains_renewal')
            && wcs_order_contains_renewal($orderId)) {
            if ($gateway || ($gateway instanceof MolliePaymentGateway)) {
                $gateway->updateOrderStatus(
                    $order,
                    $newOrderStatus,
                    sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                        __(
                            '%1$s renewal payment failed via Mollie (%2$s). You will need to manually review the payment and adjust product stocks if you use them.',
                            'mollie-payments-for-woocommerce'
                        ),
                        $paymentMethodTitle,
                        $payment->id . ($payment->mode == 'test' ? (' - ' . __(
                            'test mode',
                            'mollie-payments-for-woocommerce'
                        )) : '')
                    ),
                    $restoreStock = false
                );
            }
            $this->logger->log(
                \WC_Log_Levels::DEBUG,
                __METHOD__ . ' called for order ' . $orderId . ' and payment '
                . $payment->id . ', renewal order payment failed, order set to '
                . $newOrderStatus . ' for shop-owner review.'
            );
            // Send a "Failed order" email to notify the admin
            $emails = WC()->mailer()->get_emails();
            if (!empty($emails) && !empty($orderId)
                && !empty($emails['WC_Email_Failed_Order'])
            ) {
                $emails['WC_Email_Failed_Order']->trigger($orderId);
            }
        } elseif ($gateway || ($gateway instanceof MolliePaymentGateway)) {
            $gateway->updateOrderStatus(
                $order,
                $newOrderStatus,
                sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __(
                        '%1$s payment failed via Mollie (%2$s).',
                        'mollie-payments-for-woocommerce'
                    ),
                    $paymentMethodTitle,
                    $payment->id . ($payment->mode == 'test' ? (' - ' . __(
                        'test mode',
                        'mollie-payments-for-woocommerce'
                    )) : '')
                )
            );
        }
    }

    /**
     * @param $orderId
     * @param string $gatewayId
     * @param WC_Order $order
     */
    protected function informNotUpdatingStatus($orderId, $gatewayId, WC_Order $order)
    {
        $orderPaymentMethodTitle = get_post_meta(
            $orderId,
            '_payment_method_title',
            $single = true
        );

        // Add message to log
        $this->logger->log(
            \WC_Log_Levels::DEBUG,
            $gatewayId . ': Order ' . $order->get_id()
            . ' webhook called, but payment also started via '
            . $orderPaymentMethodTitle . ', so order status not updated.',
            [true]
        );

        // Add order note
        $order->add_order_note(
            sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __(
                    'Mollie webhook called, but payment also started via %s, so the order status is not updated.',
                    'mollie-payments-for-woocommerce'
                ),
                $orderPaymentMethodTitle
            )
        );
    }

    protected function addPaypalTransactionIdToOrder(
        WC_Order $order
    ) {

        $payment = $this->getActiveMolliePayment($order->get_id());

        if ($payment->isPaid() && $payment->details) {
            update_post_meta($order->get_id(), '_paypal_transaction_id', $payment->details->paypalReference);
            $order->add_order_note(sprintf(
                                   /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                __("Payment completed by <strong>%1\$s</strong> - %2\$s (PayPal transaction ID: %3\$s)", 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                $payment->details->consumerAccount,
                $payment->details->paypalReference
            ));
        }
    }
}
