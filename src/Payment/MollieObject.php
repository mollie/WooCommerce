<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use WC_Order;
use WC_Payment_Gateway;
use Psr\Log\LoggerInterface as Logger;
use stdClass;

class MollieObject
{
    public const MAXIMAL_LENGHT_ADDRESS = 100;
    public const MAXIMAL_LENGHT_POSTALCODE = 20;
    public const MAXIMAL_LENGHT_CITY = 200;
    public const MAXIMAL_LENGHT_REGION = 200;
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
    protected $logger;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    protected $dataService;
    protected $apiHelper;
    protected $settingsHelper;
    protected $dataHelper;
    /**
     * @var string
     */
    protected $pluginId;

    public function __construct($data, Logger $logger, PaymentFactory $paymentFactory, Api $apiHelper, Settings $settingsHelper, string $pluginId)
    {
        $this->data = $data;
        $this->logger = $logger;
        $this->paymentFactory = $paymentFactory;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->pluginId = $pluginId;
        $base_location = wc_get_base_location();
        static::$shop_country = $base_location['country'];
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
            $apiKey = $this->settingsHelper->getApiKey();
            return $this->apiHelper->getApiClient($apiKey)->payments->get($payment_id);
        } catch (ApiException $apiException) {
            $this->logger->debug(__FUNCTION__ . sprintf(': Could not load payment %s (', $payment_id) . ( $test_mode ? 'test' : 'live' ) . "): " . $apiException->getMessage() . ' (' . get_class($apiException) . ')');
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
            $apiKey = $this->settingsHelper->getApiKey();
            return $this->apiHelper->getApiClient($apiKey)->orders->get($payment_id, [ "embed" => "payments" ]);
        } catch (ApiException $e) {
            $this->logger->debug(__FUNCTION__ . sprintf(': Could not load order %s (', $payment_id) . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return null;
    }

    /**
     * @param $order
     * @param $customerId
     *
     */
    protected function getPaymentRequestData($order, $customerId, $voucherDefaultCategory = Voucher::NO_CATEGORY)
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
        if (
            class_exists('WC_Subscriptions')
            && class_exists('WC_Subscriptions_Admin')
            && $this->dataHelper->isWcSubscription($order_id)
        ) {
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
                $subscription->update_meta_data(
                    '_mollie_payment_id',
                    static::$paymentId
                );
                $subscription->update_meta_data(
                    '_mollie_payment_mode',
                    $this->data->mode
                );
                $subscription->delete_meta_data('_mollie_cancelled_payment_id');
                if (static::$customerId) {
                    $subscription->update_meta_data(
                        '_mollie_customer_id',
                        static::$customerId
                    );
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
        $mollie_payment_id = $order->get_meta('_mollie_payment_id', true);

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
                $this->getActiveMolliePaymentMode($order_id) === 'test',
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
                $this->logger->debug($exception->getMessage());
                return;
            }

            return $this->getPaymentObjectPayment(
                $mollie_order->getMolliePaymentIdFromPaymentObject(),
                $this->getActiveMolliePaymentMode($order_id) === 'test',
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

        return true;
    }

    /**
     * @return bool
     */
    protected function isOrderPaymentStartedByOtherGateway(WC_Order $order)
    {
        // Get the current payment method id for the order
        $payment_method_id = $order->get_payment_method();
        // If the current payment method id for the order is not Mollie, return true
        return strpos($payment_method_id, 'mollie') === false;
    }
    /**
     * @param WC_Order $order
     */
    public function deleteSubscriptionFromPending(WC_Order $order)
    {
        if (
            class_exists('WC_Subscriptions')
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

        if ($this->dataHelper->isSubscriptionPluginActive()) {
            $payment = isset($payment->_embedded->payments[0]) ? $payment->_embedded->payments[0] : false;
            if (
                $payment && $payment->sequenceType === 'first'
                && (property_exists($payment, 'mandateId') && $payment->mandateId !== null)
            ) {
                $order->update_meta_data(
                    '_mollie_mandate_id',
                    $payment->mandateId
                );
                $order->save();
                $subscriptions = wcs_get_subscriptions_for_renewal_order($order->get_id());
                $subscription = array_pop($subscriptions);
                if (!$subscription) {
                    return;
                }
                $subscription->update_meta_data('_mollie_payment_id', $payment->id);
                $subscription->set_payment_method('mollie_wc_gateway_' . $payment->method);
                $subscription->save();
                $subscriptionParentOrder = $subscription->get_parent();
                if ($subscriptionParentOrder) {
                    $subscriptionParentOrder->update_meta_data(
                        '_mollie_mandate_id',
                        $payment->mandateId
                    );
                    $subscriptionParentOrder->save();
                }
            }
        }
    }

    protected function addSequenceTypeForSubscriptionsFirstPayments($orderId, $gateway, $paymentRequestData): array
    {
        if ($this->dataHelper->isSubscription($orderId) || $this->dataHelper->isWcSubscription($orderId)) {
            $disable_automatic_payments = apply_filters($this->pluginId . '_is_automatic_payment_disabled', false);
            $supports_subscriptions = $gateway->supports('subscriptions');

            if ($supports_subscriptions == true && $disable_automatic_payments == false) {
                $paymentRequestData = $this->addSequenceTypeFirst($paymentRequestData);
            }
        }
        return $paymentRequestData;
    }

    public function addSequenceTypeFirst($paymentRequestData)
    {
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

        if (
            function_exists('wcs_order_contains_renewal')
            && wcs_order_contains_renewal($orderId)
        ) {
            if ($gateway instanceof MolliePaymentGateway) {
                $gateway->paymentService()->updateOrderStatus(
                    $order,
                    $newOrderStatus,
                    sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                        __(
                            '%1$s renewal payment failed via Mollie (%2$s). You will need to manually review the payment and adjust product stocks if you use them.',
                            'mollie-payments-for-woocommerce'
                        ),
                        $paymentMethodTitle,
                        $payment->id . ($payment->mode === 'test' ? (' - ' . __(
                            'test mode',
                            'mollie-payments-for-woocommerce'
                        )) : '')
                    ),
                    $restoreStock = false
                );
            }
            $this->logger->debug(
                __METHOD__ . ' called for order ' . $orderId . ' and payment '
                . $payment->id . ', renewal order payment failed, order set to '
                . $newOrderStatus . ' for shop-owner review.'
            );
            // Send a "Failed order" email to notify the admin
            $emails = WC()->mailer()->get_emails();
            if (
                !empty($emails) && !empty($orderId)
                && !empty($emails['WC_Email_Failed_Order'])
            ) {
                $emails['WC_Email_Failed_Order']->trigger($orderId);
            }
        } elseif ($gateway instanceof MolliePaymentGateway) {
            $gateway->paymentService()->updateOrderStatus(
                $order,
                $newOrderStatus,
                sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __(
                        '%1$s payment failed via Mollie (%2$s).',
                        'mollie-payments-for-woocommerce'
                    ),
                    $paymentMethodTitle,
                    $payment->id . ($payment->mode === 'test' ? (' - ' . __(
                        'test mode',
                        'mollie-payments-for-woocommerce'
                    )) : '')
                )
            );
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
        $this->logger->debug(
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
            $order->add_meta_data(
                '_paypal_transaction_id',
                $payment->details->paypalReference
            );
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
    /**
     * Get the url to return to on Mollie return
     * saves the return redirect and failed redirect, so we save the page language in case there is one set
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with order id and key as params
     */
    public function getReturnUrl($order, $returnUrl)
    {
        $returnUrl = untrailingslashit($returnUrl);
        $returnUrl = $this->asciiDomainName($returnUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();

        $onMollieReturn = 'onMollieReturn';
        $returnUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $returnUrl,
            $onMollieReturn
        );
        $returnUrl = untrailingslashit($returnUrl);
        $this->logger->debug(" Order {$orderId} returnUrl: {$returnUrl}", [true]);

        return apply_filters($this->pluginId . '_return_url', $returnUrl, $order);
    }
    /**
     * Get the webhook url
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with gateway and order id and key as params
     */
    public function getWebhookUrl($order, $gatewayId)
    {
        $webhookUrl = WC()->api_request_url($gatewayId);
        $webhookUrl = untrailingslashit($webhookUrl);
        $webhookUrl = $this->asciiDomainName($webhookUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $webhookUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $webhookUrl
        );
        $webhookUrl = untrailingslashit($webhookUrl);

        $this->logger->debug(" Order {$orderId} webhookUrl: {$webhookUrl}", [true]);

        return apply_filters($this->pluginId . '_webhook_url', $webhookUrl, $order);
    }
    /**
     * @param $url
     *
     * @return string
     */
    protected function asciiDomainName($url): string
    {
        $parsed = parse_url($url);
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : '';
        $domain = isset($parsed['host']) ? $parsed['host'] : false;
        $query = isset($parsed['query']) ? $parsed['query'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        if (!$domain) {
            return $url;
        }

        if (function_exists('idn_to_ascii')) {
            $domain = $this->idnEncodeDomain($domain);
            $url = $scheme . "://" . $domain . $path . '?' . $query;
        }

        return $url;
    }
    /**
     * @param $order_id
     * @param $order_key
     * @param $webhook_url
     * @param string $filterFlag
     *
     * @return string
     */
    protected function appendOrderArgumentsToUrl($order_id, $order_key, $webhook_url, $filterFlag = '')
    {
        $webhook_url = add_query_arg(
            [
                'order_id' => $order_id,
                'key' => $order_key,
                'filter_flag' => $filterFlag,
            ],
            $webhook_url
        );
        return $webhook_url;
    }

    /**
     * @param $domain
     * @return false|mixed|string
     */
    protected function idnEncodeDomain($domain)
    {
        if (
            defined('IDNA_NONTRANSITIONAL_TO_ASCII')
            && defined(
                'INTL_IDNA_VARIANT_UTS46'
            )
        ) {
            $domain = idn_to_ascii(
                $domain,
                IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            ) ? idn_to_ascii(
                $domain,
                IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            ) : $domain;
        } else {
            $domain = idn_to_ascii($domain) ? idn_to_ascii($domain) : $domain;
        }
        return $domain;
    }

    protected function getPaymentDescription($order, $option)
    {
        $description = !$option ? '' : trim($option);
        $description = !$description ? '{orderNumber}' : $description;

        switch ($description) {
            // Support for old deprecated options.
            // TODO: remove when deprecated
            case '{orderNumber}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Order {orderNumber}',
                        'Payment description for {orderNumber}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{storeName}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'StoreName {storeName}',
                        'Payment description for {storeName}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.firstname}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Customer Firstname {customer.firstname}',
                        'Payment description for {customer.firstname}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.lastname}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Customer Lastname {customer.lastname}',
                        'Payment description for {customer.lastname}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.company}':
                $description =
                /* translators: do not translate between {} */
                    _x(
                        'Customer Company {customer.company}',
                        'Payment description for {customer.company}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            // Support for custom string with interpolation.
            default:
                // Replace available description tags.
                $description = $this->replaceTagsDescription($order, $description);
                break;
        }

        // Fall back on default if description turns out empty.
        return !$description ? __('Order', 'woocommerce') . ' ' . $order->get_order_number() : $description;
    }

    /**
     * @param $order
     * @param $description
     * @return array|string|string[]
     */
    protected function replaceTagsDescription($order, $description)
    {
        $replacement_tags = [
            '{orderNumber}' => $order->get_order_number(),
            '{storeName}' => get_bloginfo('name'),
            '{customer.firstname}' => $order->get_billing_first_name(),
            '{customer.lastname}' => $order->get_billing_last_name(),
            '{customer.company}' => $order->get_billing_company(),
        ];
        foreach ($replacement_tags as $tag => $replacement) {
            $description = str_replace($tag, $replacement, $description);
        }
        return $description;
    }

    /**
     * @param $order
     * @return stdClass
     */
    protected function createBillingAddress($order)
    {
        // Setup billing and shipping objects
        $billingAddress = new stdClass();

        // Get user details
        $billingAddress->givenName = (ctype_space(
            $order->get_billing_first_name()
        )) ? null : $order->get_billing_first_name();
        $billingAddress->familyName = (ctype_space(
            $order->get_billing_last_name()
        )) ? null : $order->get_billing_last_name();
        $billingAddress->email = (ctype_space($order->get_billing_email()))
            ? null : $order->get_billing_email();
        // Create billingAddress object
        $billingAddress->streetAndNumber = (ctype_space(
            $order->get_billing_address_1()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_1(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $billingAddress->streetAdditional = (ctype_space(
            $order->get_billing_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_2(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $billingAddress->postalCode = (ctype_space(
            $order->get_billing_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_postcode(),
                self::MAXIMAL_LENGHT_POSTALCODE
            );
        $billingAddress->city = (ctype_space($order->get_billing_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_city(),
                self::MAXIMAL_LENGHT_CITY
            );
        $billingAddress->region = (ctype_space($order->get_billing_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_state(),
                self::MAXIMAL_LENGHT_REGION
            );
        $billingAddress->country = (ctype_space($order->get_billing_country()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_country(),
                self::MAXIMAL_LENGHT_REGION
            );
        $billingAddress->organizationName = $this->billingCompanyField($order);
        $phone = $this->getPhoneNumber($order);
        $billingAddress->phone = (ctype_space($phone))
            ? null
            : $this->getFormatedPhoneNumber($phone);
        return $billingAddress;
    }

    protected function getPhoneNumber($order)
    {

        $phone = !empty($order->get_billing_phone()) ? $order->get_billing_phone() : $order->get_shipping_phone();
        if (empty($phone)) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $phone =  wc_clean(wp_unslash($_POST['billing_phone'] ?? ''));
        }
        return $phone;
    }

    protected function getFormatedPhoneNumber(string $phone)
    {
        //remove whitespaces and all non numerical characters except +
        $phone = preg_replace('/[^0-9+]+/', '', $phone);
        if (!is_string($phone)) {
            return null;
        }
        //check if phone starts with 06 and replace with +316
        $phone = transformPhoneToNLFormat($phone);

        //check that $phone is in E164 format or can be changed by api
        if (is_string($phone) && preg_match('/^\+[1-9]\d{10,13}$|^[1-9]\d{9,13}$/', $phone)) {
            return $phone;
        }
        return null;
    }

    /**
     * @param $order
     * @return string|null
     */
    public function billingCompanyField($order): ?string
    {
        if (!trim($order->get_billing_company())) {
            return $this->checkBillieCompanyField($order);
        }
        return $this->maximalFieldLengths(
            $order->get_billing_company(),
            self::MAXIMAL_LENGHT_ADDRESS
        );
    }

    private function checkBillieCompanyField($order)
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !$gateway->id) {
            return null;
        }
        $isBillieMethodId = $gateway->id === 'mollie_wc_gateway_billie';
        if ($isBillieMethodId) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST["billing_company"] ?? ''));
            if ($fieldPosted === '' || !is_string($fieldPosted)) {
                return null;
            }
            return $this->maximalFieldLengths(
                $fieldPosted,
                self::MAXIMAL_LENGHT_ADDRESS
            );
        }
        return null;
    }

    /**
     * @param $order
     * @return stdClass
     */
    protected function createShippingAddress($order)
    {
        $shippingAddress = new stdClass();
        // Get user details
        $shippingAddress->givenName = (ctype_space(
            $order->get_shipping_first_name()
        )) ? null : $order->get_shipping_first_name();
        $shippingAddress->familyName = (ctype_space(
            $order->get_shipping_last_name()
        )) ? null : $order->get_shipping_last_name();
        $shippingAddress->email = (ctype_space($order->get_billing_email()))
            ? null
            : $order->get_billing_email(); // WooCommerce doesn't have a shipping email


        // Create shippingAddress object
        $shippingAddress->streetAndNumber = (ctype_space(
            $order->get_shipping_address_1()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_1(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $shippingAddress->streetAdditional = (ctype_space(
            $order->get_shipping_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_2(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $shippingAddress->postalCode = (ctype_space(
            $order->get_shipping_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_postcode(),
                self::MAXIMAL_LENGHT_POSTALCODE
            );
        $shippingAddress->city = (ctype_space($order->get_shipping_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_city(),
                self::MAXIMAL_LENGHT_CITY
            );
        $shippingAddress->region = (ctype_space($order->get_shipping_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_state(),
                self::MAXIMAL_LENGHT_REGION
            );
        $shippingAddress->country = (ctype_space(
            $order->get_shipping_country()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_country(),
                self::MAXIMAL_LENGHT_REGION
            );
        return $shippingAddress;
    }

    /**
     * Method that shortens the field to a certain length
     *
     * @param string $field
     * @param int    $maximalLength
     *
     * @return null|string
     */
    protected function maximalFieldLengths($field, $maximalLength)
    {
        if (!is_string($field)) {
            return null;
        }
        if (is_int($maximalLength) && strlen($field) > $maximalLength) {
            $field = substr($field, 0, $maximalLength);
            $field = !$field ? null : $field;
        }

        return $field;
    }
}
