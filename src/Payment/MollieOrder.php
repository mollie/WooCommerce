<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use DateTime;
use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Log\LogLevel;
use stdClass;
use WC_Order;
use WP_Error;

class MollieOrder extends MollieObject
{
    public const ACTION_AFTER_REFUND_AMOUNT_CREATED = 'mollie-payments-for-woocommerce' . '_refund_amount_created';
    public const ACTION_AFTER_REFUND_ORDER_CREATED = 'mollie-payments-for-woocommerce' . '_refund_order_created';
    public const MAXIMAL_LENGHT_ADDRESS = 100;
    public const MAXIMAL_LENGHT_POSTALCODE = 20;
    public const MAXIMAL_LENGHT_CITY = 200;
    public const MAXIMAL_LENGHT_REGION = 200;

    protected static $paymentId;
    protected static $customerId;
    protected static $order;
    protected static $payment;
    protected static $shop_country;
    /**
     * @var OrderLines
     */
    protected $orderLines;

    /**
     * @var OrderItemsRefunder
     */
    private $orderItemsRefunder;
    protected $pluginId;

    /**
     * MollieOrder constructor.
     * @param OrderItemsRefunder $orderItemsRefunder
     * @param $data
     */
    public function __construct(OrderItemsRefunder $orderItemsRefunder, $data, $pluginId, Api $apiHelper, $settingsHelper, $dataHelper, $logger, OrderLines $orderLines)
    {
        $this->data = $data;
        $this->orderItemsRefunder = $orderItemsRefunder;
        $this->pluginId = $pluginId;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->orderLines = $orderLines;
    }

    public function getPaymentObject($paymentId, $testMode = false, $useCache = true)
    {
        try {
            $testMode = $this->settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey();
            self::$payment = $this->apiHelper->getApiClient($apiKey)->orders->get($paymentId, [ "embed" => "payments,refunds" ]);

            return parent::getPaymentObject($paymentId, $testMode = false, $useCache = true);
        } catch (ApiException $e) {
            $this->logger->debug(__CLASS__ . __FUNCTION__ . ": Could not load payment $paymentId (" . ( $testMode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return null;
    }

    /**
     * @param $order
     * @param $customerId
     *
     * @return array
     */
    public function getPaymentRequestData($order, $customerId, $voucherDefaultCategory = Voucher::NO_CATEGORY)
    {
        $settingsHelper = $this->settingsHelper;
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $storeCustomer = $settingsHelper->shouldStoreCustomer();

        $gateway = wc_get_payment_gateway_by_order($order);

        if (! $gateway || ! ( $gateway instanceof MolliePaymentGateway )) {
            return  [ 'result' => 'failure' ];
        }

        $gatewayId = $gateway->id;
        $selectedIssuer = $gateway->getSelectedIssuer();
        $returnUrl = $gateway->get_return_url($order);
        $returnUrl = $this->getReturnUrl($order, $returnUrl);
        $webhookUrl = $this->getWebhookUrl($order, $gatewayId);
        $isPayPalExpressOrder = $order->get_meta('_mollie_payment_method_button') === 'PayPalButton';
        $billingAddress = null;
        if (!$isPayPalExpressOrder) {
            $billingAddress = $this->createBillingAddress($order);
            $shippingAddress = $this->createShippingAddress($order);
        }

        // Generate order lines for Mollie Orders
        $orderLinesHelper = $this->orderLines;
        $orderLines = $orderLinesHelper->order_lines($order, $voucherDefaultCategory);

        // Build the Mollie order data
        $paymentRequestData = [
            'amount' => [
                'currency' => $this->dataHelper->getOrderCurrency($order),
                'value' => $this->dataHelper->formatCurrencyValue(
                    $order->get_total(),
                    $this->dataHelper->getOrderCurrency($order)
                ),
            ],
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'method' => $gateway->paymentMethod()->getProperty('id'),
            'payment' => [
                'issuer' => $selectedIssuer,
            ],
            'locale' => $paymentLocale,
            'billingAddress' => $billingAddress,
            'metadata' => apply_filters(
                $this->pluginId . '_payment_object_metadata',
                [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                ]
            ),
            'lines' => $orderLines['lines'],
            'orderNumber' => $order->get_order_number(),
        ];

        $paymentRequestData = $this->addSequenceTypeForSubscriptionsFirstPayments($order->get_id(), $gateway, $paymentRequestData);

        // Only add shippingAddress if all required fields are set
        if (
            !empty($shippingAddress->streetAndNumber)
            && !empty($shippingAddress->postalCode)
            && !empty($shippingAddress->city)
            && !empty($shippingAddress->country)
        ) {
            $paymentRequestData['shippingAddress'] = $shippingAddress;
        }

        // Only store customer at Mollie if setting is enabled
        if ($storeCustomer) {
            $paymentRequestData['payment']['customerId'] = $customerId;
        }

        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($paymentRequestData['payment'])) {
            $paymentRequestData['payment']['cardToken'] = $cardToken;
        }
        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $applePayToken = wc_clean(wp_unslash($_POST["token"] ?? ''));
        if ($applePayToken && isset($paymentRequestData['payment'])) {
            $encodedApplePayToken = json_encode($applePayToken);
            $paymentRequestData['payment']['applePayPaymentToken'] = $encodedApplePayToken;
        }
        $customerBirthdate = $this->getCustomerBirthdate($order);
        if ($customerBirthdate) {
            $paymentRequestData['consumerDateOfBirth'] = $customerBirthdate;
        }
        return $paymentRequestData;
    }

    public function setActiveMolliePayment($orderId)
    {
        self::$paymentId = $this->getMolliePaymentIdFromPaymentObject();
        self::$customerId = $this->getMollieCustomerIdFromPaymentObject();

        self::$order = wc_get_order($orderId);
        self::$order->update_meta_data('_mollie_order_id', $this->data->id);
        self::$order->save();

        return parent::setActiveMolliePayment($orderId);
    }

    public function getMolliePaymentIdFromPaymentObject()
    {
        $payment = $this->getPaymentObject($this->data->id);

        if (isset($payment->_embedded->payments[0]->id)) {
            return $payment->_embedded->payments[0]->id;
        }
    }

    public function getMollieCustomerIdFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->_embedded->payments[0]->customerId)) {
            return $payment->_embedded->payments[0]->customerId;
        }
    }

    public function getSequenceTypeFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->_embedded->payments[0]->sequenceType)) {
            return $payment->_embedded->payments[0]->sequenceType;
        }
    }

    public function getMollieCustomerIbanDetailsFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);
        $ibanDetails = [];

        if (isset($payment->_embedded->payments[0]->id)) {
            $actualPayment = new MolliePayment($payment->_embedded->payments[0]->id, $this->pluginId, $this->apiHelper, $this->settingsHelper, $this->dataHelper, $this->logger);
            $actualPayment = $actualPayment->getPaymentObject($actualPayment->data);
            /**
             * @var Payment $actualPayment
             */
            $ibanDetails['consumerName'] = $actualPayment->details->consumerName;
            $ibanDetails['consumerAccount'] = $actualPayment->details->consumerAccount;
        }

        return $ibanDetails;
    }

    public function addSequenceTypeFirst($paymentRequestData)
    {
        $paymentRequestData['payment']['sequenceType'] = 'first';
        return $paymentRequestData;
    }

    /**
     * @param WC_Order                   $order
     * @param Order $payment
     * @param string                     $paymentMethodTitle
     */
    public function onWebhookPaid(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        if ($payment->isPaid()) {
            // Add messages to log
            $this->logger->debug(__METHOD__ . " called for order {$orderId}");

            if ($payment->method === 'paypal') {
                $this->addPaypalTransactionIdToOrder($order);
            }

            $order->payment_complete($payment->id);

            // Add messages to log
            $this->logger->debug(
                __METHOD__ .
                ' WooCommerce payment_complete() processed and returned to ' .
                __METHOD__ . " for order {$orderId}"
            );

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed using %1$s payment (%2$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id .
                ( $payment->mode === 'test' ?
                    ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
            ));

            // Mark the order as processed and paid via Mollie
            $this->setOrderPaidAndProcessed($order);

            // Remove (old) cancelled payments from this order
            $this->unsetCancelledMolliePaymentId($orderId);

            // Add messages to log
            $this->logger->debug(
                __METHOD__ .
                " processing paid order via Mollie plugin fully completed for order {$orderId}"
            );
            //update payment so it can be refunded directly
            $this->updatePaymentDataWithOrderData($payment, $orderId);
            // Add a message to log
            $this->logger->debug(
                __METHOD__ . ' updated payment with webhook and metadata '
            );

            // Subscription processing
            $this->addMandateIdMetaToFirstPaymentSubscriptionOrder($order, $payment);
            $this->deleteSubscriptionFromPending($order);
        } else {
            // Add messages to log
            $this->logger->debug(
                __METHOD__ .
                " payment at Mollie not paid, so no processing for order {$orderId}"
            );
        }
    }

    /**
     * @param WC_Order                   $order
     * @param Order $payment
     * @param string                     $paymentMethodTitle
     */
    public function onWebhookAuthorized(WC_Order $order, $payment, $paymentMethodTitle)
    {
        // Get order ID in the correct way depending on WooCommerce version
        $orderId = $order->get_id();

        if ($payment->isAuthorized()) {
            // Add messages to log
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);

            // WooCommerce 2.2.0 has the option to store the Payment transaction id.
            $order->payment_complete($payment->id);

            // Add messages to log
            $this->logger->debug(__METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $orderId);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order authorized using %1$s payment (%2$s). Set order to completed in WooCommerce when you have shipped the products, to capture the payment. Do this within 28 days, or the order will expire. To handle individual order lines, process the order via the Mollie Dashboard.', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
            ));

            // Mark the order as processed and paid via Mollie
            $this->setOrderPaidAndProcessed($order);

            // Remove (old) cancelled payments from this order
            $this->unsetCancelledMolliePaymentId($orderId);

            // Add messages to log
            $this->logger->debug(__METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $orderId);

            // Subscription processing
            $this->deleteSubscriptionFromPending($order);
        } else {
            // Add messages to log
            $this->logger->debug(__METHOD__ . ' order at Mollie not authorized, so no processing for order ' . $orderId);
        }
    }

    /**
     * @param WC_Order                   $order
     * @param Order $payment
     * @param string                     $paymentMethodTitle
     */
    public function onWebhookCompleted(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();

        if ($payment->isCompleted()) {
            // Add messages to log
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);

            if ($payment->method === 'paypal') {
                $this->addPaypalTransactionIdToOrder($order);
            }
            add_filter('woocommerce_valid_order_statuses_for_payment_complete', static function ($statuses) {
                $statuses[] = 'processing';
                return $statuses;
            });
            add_filter('woocommerce_payment_complete_order_status', static function ($status) use ($order) {
                return $order->get_status() === 'processing' ? 'completed' : $status;
            });
            $order->payment_complete($payment->id);
            // Add messages to log
            $this->logger->debug(__METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $orderId);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed at Mollie for %1$s order (%2$s). At least one order line completed. Remember: Completed status for an order at Mollie is not the same as Completed status in WooCommerce!', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
            ));

            // Mark the order as processed and paid via Mollie
            $this->setOrderPaidAndProcessed($order);

            // Remove (old) cancelled payments from this order
            $this->unsetCancelledMolliePaymentId($orderId);

            // Add messages to log
            $this->logger->debug(__METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $orderId);

            // Subscription processing
            $this->deleteSubscriptionFromPending($order);
        } else {
            // Add messages to log
            $this->logger->debug(__METHOD__ . ' order at Mollie not completed, so no further processing for order ' . $orderId);
        }
    }

    /**
     * @param WC_Order                   $order
     * @param Order $payment
     * @param string                     $paymentMethodTitle
     */
    public function onWebhookCanceled(WC_Order $order, $payment, $paymentMethodTitle)
    {
        // Get order ID in the correct way depending on WooCommerce version
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->debug(__METHOD__ . " called for order {$orderId}");

        // if the status is Completed|Refunded|Cancelled  DONT change the status to cancelled
        if ($this->isFinalOrderStatus($order)) {
            $this->logger->debug(
                __METHOD__
                . " called for payment {$orderId} has final status. Nothing to be done"
            );

            return;
        }

        //status is Pending|Failed|Processing|On-hold so Cancel
        $this->unsetActiveMolliePayment($orderId, $payment->id);
        $this->setCancelledMolliePaymentId($orderId, $payment->id);

        // What status does the user want to give orders with cancelled payments?
        $settingsHelper = $this->settingsHelper;
        $orderStatusCancelledPayments = $settingsHelper->getOrderStatusCancelledPayments();

        // New order status
        if ($orderStatusCancelledPayments === 'pending' || $orderStatusCancelledPayments === null) {
            $newOrderStatus = SharedDataDictionary::STATUS_PENDING;
        } elseif ($orderStatusCancelledPayments === 'cancelled') {
            $newOrderStatus = SharedDataDictionary::STATUS_CANCELLED;
        }
        // if I cancel manually the order is canceled in Woo before calling Mollie
        if ($order->get_status() === 'cancelled') {
            $newOrderStatus = SharedDataDictionary::STATUS_CANCELLED;
        }

        // Overwrite plugin-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_cancelled', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_cancelled_' . $payment->method, $newOrderStatus);

        // Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus(
            $order,
            $newOrderStatus,
            $paymentMethodTitle,
            $payment
        );

        // User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%1$s order (%2$s) cancelled .', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
        ));
        $this->deleteSubscriptionFromPending($order);
    }

    /**
     * @param WC_Order                   $order
     * @param Order $payment
     * @param string                     $paymentMethodTitle
     */
    public function onWebhookFailed(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);

        // New order status
        $newOrderStatus = SharedDataDictionary::STATUS_FAILED;

        // Overwrite plugin-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed_' . $payment->method, $newOrderStatus);

        $gateway = wc_get_payment_gateway_by_order($order);

        // If WooCommerce Subscriptions is installed, process this failure as a subscription, otherwise as a regular order
        // Update order status for order with failed payment, don't restore stock
        $this->failedSubscriptionProcess(
            $orderId,
            $gateway,
            $order,
            $newOrderStatus,
            $paymentMethodTitle,
            $payment
        );

        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', regular order payment failed.');
    }

    /**
     * @param WC_Order                   $order
     * @param Order $payment
     * @param string                     $paymentMethodTitle
     */
    public function onWebhookExpired(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        $molliePaymentId = $order->get_meta('_mollie_order_id', true);

        // Add messages to log
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);
        // Check that this order has not been marked paid already
        if (!$order->needs_payment()) {
            $this->logger->log(
                LogLevel::DEBUG,
                __METHOD__ . ' called for order ' . $orderId . ', not processed because the order is already paid.'
            );

            return;
        }
        // Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
        if ($molliePaymentId !== $payment->id) {
            $this->logger->debug(__METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $molliePaymentId);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s order expired (%2$s) but not cancelled because of another pending payment (%3$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' ),
                $molliePaymentId
            ));

            return;
        }

        // New order status
        $newOrderStatus = SharedDataDictionary::STATUS_CANCELLED;

        // Overwrite plugin-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_expired', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_expired_' . $payment->method, $newOrderStatus);

        // Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus(
            $order,
            $newOrderStatus,
            $paymentMethodTitle,
            $payment
        );

        // Remove (old) cancelled payments from this order
        $this->unsetCancelledMolliePaymentId($orderId);

        // Subscription processing
        $this->deleteSubscriptionFromPending($order);
    }

    /**
     * Process a payment object refund
     *
     * @param WC_Order $order
     * @param int      $orderId
     * @param object   $paymentObject
     * @param null     $amount
     * @param string   $reason
     *
     * @return bool|WP_Error
     */
    public function refund(WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '')
    {
        $this->logger->debug(__METHOD__ . ' - ' . $orderId . ' - Try to process refunds or cancels.');

        try {
            $paymentObject = $this->getPaymentObject($paymentObject->data);

            if (! $paymentObject) {
                $errorMessage = "Could not find active Mollie order for WooCommerce order ' . $orderId";

                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);

                throw new Exception($errorMessage);
            }

            if (! ( $paymentObject->isPaid() || $paymentObject->isAuthorized() || $paymentObject->isCompleted() )) {
                $errorMessage = "Can not cancel or refund $paymentObject->id as order $orderId has status " . ucfirst($paymentObject->status) . ", it should be at least Paid, Authorized or Completed.";

                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);

                throw new Exception($errorMessage);
            }

            // Get all existing refunds
            $refunds = $order->get_refunds();

            // Get latest refund
            $woocommerceRefund = wc_get_order($refunds[0]);

            // Get order items from refund
            $items = $woocommerceRefund->get_items([ 'line_item', 'fee', 'shipping' ]);

            if (empty($items)) {
                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }

            // Compare total amount of the refund to the combined totals of all refunded items,
            // if the refund total is greater than sum of refund items, merchant is also doing a
            // 'Refund amount', which the Mollie API does not support. In that case, stop entire
            // process and warn the merchant.

            $totals = 0;

            foreach ($items as $itemId => $itemData) {
                $totals += $itemData->get_total() + $itemData->get_total_tax();
            }

            $totals = number_format(abs($totals), 2); // WooCommerce - sum of all refund items
            $checkAmount = $amount ? number_format((float)$amount, 2) : 0; // WooCommerce - refund amount

            if ($checkAmount !== $totals) {
                $errorMessage = _x('The sum of refunds for all order lines is not identical to the refund amount, so this refund will be processed as a payment amount refund, not an order line refund.', 'Order note error', 'mollie-payments-for-woocommerce');
                $order->add_order_note($errorMessage);
                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);

                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }

            $this->logger->debug('Try to process individual order item refunds or cancels.');

            try {
                return $this->orderItemsRefunder->refund(
                    $order,
                    $items,
                    $paymentObject,
                    $reason
                );
            } catch (PartialRefundException $exception) {
                $this->logger->debug(__METHOD__ . ' - ' . $exception->getMessage());
                return $this->refund_amount(
                    $order,
                    $amount,
                    $paymentObject,
                    $reason
                );
            }
        } catch (Exception $exception) {
            $exceptionMessage = $exception->getMessage();
            $this->logger->debug(__METHOD__ . ' - ' . $exceptionMessage);
            return new WP_Error(1, $exceptionMessage);
        }

        return false;
    }

    /**
     * @param $order
     * @param $orderId
     * @param $amount
     * @param $items
     * @param $paymentObject
     * @param $reason
     *
     * @return bool
     * @throws ApiException
     * @deprecated Not recommended because merchant will be charged for every refunded item, use OrderItemsRefunder instead.
     */
    public function refund_order_items($order, $orderId, $amount, $items, $paymentObject, $reason)
    {
        $this->logger->debug('Try to process individual order item refunds or cancels.');

        // Try to do the actual refunds or cancellations

        // Loop through items in the WooCommerce refund
        foreach ($items as $key => $item) {
            // Some merchants update orders with an order line with value 0, in that case skip processing that order line.
            $itemRefundAmountPrecheck = abs($item->get_total() + $item->get_total_tax());
            if ($itemRefundAmountPrecheck === 0) {
                continue;
            }

            // Loop through items in the Mollie payment object (Order)
            foreach ($paymentObject->lines as $line) {
                // If there is no metadata wth the order item ID, this order can't process individual order lines
                if (empty($line->metadata->order_item_id)) {
                    $noteMessage = 'Refunds for this specific order can not be processed per order line. Trying to process this as an amount refund instead.';
                    $this->logger->debug(__METHOD__ . " - " . $noteMessage);

                    return $this->refund_amount($order, $amount, $paymentObject, $reason);
                }

                // Get the Mollie order line information that we need later
                $originalOrderItemId = $item->get_meta('_refunded_item_id', true);
                $itemRefundAmount = abs($item->get_total() + $item->get_total_tax());

                if ($originalOrderItemId === $line->metadata->order_item_id) {
                    // Calculate the total refund amount for one order line
                    $lineTotalRefundAmount = abs($item->get_quantity()) * $line->unitPrice->value;

                    // Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line, so when merchants try that, warn them and block the process
                    if ((number_format($lineTotalRefundAmount, 2) != number_format($itemRefundAmount, 2)) || ( abs($item->get_quantity()) < 1 )) {
                        $noteMessage = sprintf(
                            "Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line. Use 'Refund amount' instead. The WooCommerce order item ID is %s, Mollie order line ID is %s.",
                            $originalOrderItemId,
                            $line->id
                        );

                        $this->logger->debug(__METHOD__ . " - Order $orderId: " . $noteMessage);
                        throw new Exception($noteMessage);
                    }

                    $this->processOrderItemsRefund(
                        $paymentObject,
                        $item,
                        $line,
                        $order,
                        $orderId,
                        $itemRefundAmount,
                        $reason,
                        $items
                    );
                }
            }
        }

        return true;
    }

    /**
     * @param $order
     * @param $order_id
     * @param $amount
     * @param $paymentObject
     * @param $reason
     *
     * @return bool
     * @throws ApiException|Exception
     */
    public function refund_amount($order, $amount, $paymentObject, $reason)
    {
        $orderId = $order->get_id();

        $this->logger->debug('Try to process an amount refund (not individual order line)');

        $paymentObjectPayment = $this->getActiveMolliePayment(
            $orderId
        );

        $apiKey = $this->settingsHelper->getApiKey();

        if ($paymentObject->isCreated() || $paymentObject->isAuthorized() || $paymentObject->isShipping()) {
            /* translators: Placeholder 1: payment status.*/
            $noteMessage = sprintf(
                _x(
                    'Can not refund order amount that has status %1$s at Mollie.',
                    'Order note error',
                    'mollie-payments-for-woocommerce'
                ),
                ucfirst($paymentObject->status)
            );
            $order->add_order_note($noteMessage);
            $this->logger->debug(__METHOD__ . ' - ' . $noteMessage);
            throw new Exception($noteMessage);
        }

        if ($paymentObject->isPaid() || $paymentObject->isShipping() || $paymentObject->isCompleted()) {
            $refund = $this->apiHelper->getApiClient($apiKey)->payments->refund($paymentObjectPayment, [
                'amount' =>  [
                    'currency' => $this->dataHelper->getOrderCurrency($order),
                    'value' => $this->dataHelper->formatCurrencyValue($amount, $this->dataHelper->getOrderCurrency($order)),
                ],
                'description' => $reason,
            ]);
            /* translators: Placeholder 1: Currency. Placeholder 2: Refund amount. Placeholder 3: Reason. Placeholder 4: Refund id.*/
            $noteMessage = sprintf(
                __('Amount refund of %1$s%2$s refunded in WooCommerce and at Mollie.%3$s Refund ID: %4$s.', 'mollie-payments-for-woocommerce'),
                $this->dataHelper->getOrderCurrency($order),
                $amount,
                ( ! empty($reason) ? ' Reason: ' . $reason . '.' : '' ),
                $refund->id
            );

            $order->add_order_note($noteMessage);
            $this->logger->debug($noteMessage);

            /**
             * After Refund Amount Created
             *
             * @param Refund $refund
             * @param WC_Order $order
             * @param string $amount
             */
            do_action(self::ACTION_AFTER_REFUND_AMOUNT_CREATED, $refund, $order, $amount);

            do_action_deprecated(
                $this->pluginId . '_refund_created',
                [$refund, $order],
                '5.3.1',
                self::ACTION_AFTER_REFUND_AMOUNT_CREATED
            );

            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     * @param int                     $orderId
     */
    public function updatePaymentDataWithOrderData($order, $orderId)
    {
        $paymentCollection = $order->payments();
        foreach ($paymentCollection as $payment) {
            $payment->webhookUrl = $order->webhookUrl;
            $payment->metadata = ['order_id' => $orderId];
            $payment->update();
        }
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

    /**
     * @param WC_Order                    $order
     * @param                             $newOrderStatus
     * @param                             $orderId
     * @param                             $paymentMethodTitle
     * @param Order $payment
     */
    protected function maybeUpdateStatus(
        WC_Order $order,
        $newOrderStatus,
        $paymentMethodTitle,
        Order $payment
    ) {

        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$this->isOrderPaymentStartedByOtherGateway($order) && is_a($gateway, MolliePaymentGateway::class)) {
            $gateway->paymentService()->updateOrderStatus($order, $newOrderStatus);
        } else {
            $this->informNotUpdatingStatus($gateway->id, $order);
        }

        $order->add_order_note(
            sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __(
                    '%1$s order (%2$s) expired .',
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
        $phone = !empty($order->get_billing_phone()) ? $order->get_billing_phone() : $order->get_shipping_phone();
        $billingAddress->phone = (ctype_space($phone))
            ? null
            : $this->getFormatedPhoneNumber($phone);
        return $billingAddress;
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
     * @param $paymentObject
     * @param $item
     * @param $line
     * @param $order
     * @param $orderId
     * @param $itemRefundAmount
     * @param $reason
     * @param $items
     *
     * @throws ApiException
     */
    protected function processOrderItemsRefund(
        $paymentObject,
        $item,
        $line,
        $order,
        $orderId,
        $itemRefundAmount,
        $reason,
        $items
    ): void {

        $apiKey = $this->settingsHelper->getApiKey();

        // Get the Mollie order
        $mollieOrder = $this->apiHelper->getApiClient($apiKey)->orders->get($paymentObject->id);

        $itemTotalAmount = abs(number_format($item->get_total() + $item->get_total_tax(), 2));

        // Prepare the order line to update
        if (!empty($line->discountAmount)) {
            $lines = [
                'lines' => [
                    [
                        'id' => $line->id,
                        'quantity' => abs($item->get_quantity()),
                        'amount' => [
                            'value' => $this->dataHelper->formatCurrencyValue(
                                $itemTotalAmount,
                                $this->dataHelper->getOrderCurrency(
                                    $order
                                )
                            ),
                            'currency' => $this->dataHelper->getOrderCurrency($order),
                        ],
                    ],
                ],
            ];
        } else {
            $lines = [
                'lines' => [
                    [
                        'id' => $line->id,
                        'quantity' => abs($item->get_quantity()),
                    ],
                ],
            ];
        }

        if ($line->status === 'created' || $line->status === 'authorized') {
            // Returns null if successful.
            $refund = $mollieOrder->cancelLines($lines);

            $this->logger->debug(
                __METHOD__ . ' - Cancelled order line: ' . abs($item->get_quantity()) . 'x ' . $item->get_name(
                ) . '. Mollie order line: ' . $line->id . ', payment object: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . $this->data->getOrderCurrency(
                    $order
                ) . wc_format_decimal($itemRefundAmount) . (!empty($reason) ? ', reason: ' . $reason : '')
            );

            if ($refund === null) {
                /* translators: Placeholder 1: Number of items. Placeholder 2: Name of item. Placeholder 3: Currency. Placeholder 4: Amount.*/
                $noteMessage = sprintf(
                    __(
                        '%1$sx %2$s cancelled for %3$s%4$s in WooCommerce and at Mollie.',
                        'mollie-payments-for-woocommerce'
                    ),
                    abs($item->get_quantity()),
                    $item->get_name(),
                    $this->dataHelper->getOrderCurrency($order),
                    $itemRefundAmount
                );
            }
        }

        if ($line->status === 'paid' || $line->status === 'shipping' || $line->status === 'completed') {
            $lines['description'] = $reason;
            $refund = $mollieOrder->refund($lines);

            $this->logger->debug(
                __METHOD__ . ' - Refunded order line: ' . abs($item->get_quantity()) . 'x ' . $item->get_name(
                ) . '. Mollie order line: ' . $line->id . ', payment object: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . $this->data->getOrderCurrency(
                    $order
                ) . wc_format_decimal($itemRefundAmount) . (!empty($reason) ? ', reason: ' . $reason : '')
            );
            /* translators: Placeholder 1: Number of items. Placeholder 2: Name of item. Placeholder 3: Currency. Placeholder 4: Amount. Placeholder 5: Reason. Placeholder 6: Refund Id. */
            $noteMessage = sprintf(
                __(
                    '%1$sx %2$s refunded for %3$s%4$s in WooCommerce and at Mollie.%5$s Refund ID: %6$s.',
                    'mollie-payments-for-woocommerce'
                ),
                abs($item->get_quantity()),
                $item->get_name(),
                $this->dataHelper->getOrderCurrency($order),
                $itemRefundAmount,
                (!empty($reason) ? ' Reason: ' . $reason . '.' : ''),
                $refund->id
            );
        }

        do_action(
            self::ACTION_AFTER_REFUND_ORDER_CREATED,
            $refund,
            $order
        );

        do_action_deprecated(
            $this->pluginId . '_refund_created',
            [$refund, $order],
            '5.3.1',
            self::ACTION_AFTER_REFUND_PAYMENT_CREATED
        );

        $order->add_order_note($noteMessage);
        $this->logger->debug($noteMessage);

        // drop item from array
        unset($items[$item->get_id()]);
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

    protected function getCustomerBirthdate($order)
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !isset($gateway->id)) {
            return null;
        }
        $methodId = $gateway->id === 'mollie_wc_gateway_in3';
        if ($methodId) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST["billing_birthdate"] ?? ''));
            if ($fieldPosted === '' || !is_string($fieldPosted)) {
                return null;
            }
            $format = "Y-m-d";
            return date($format, (int) strtotime($fieldPosted));
        }
        return null;
    }

    protected function getFormatedPhoneNumber(string $phone)
    {
        //remove whitespaces and all non numerical characters except +
        $phone = preg_replace('/[^0-9+]+/', '', $phone);

        //check that $phone is in E164 format
        if ($phone !== null && preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            return $phone;
        }
        return null;
    }
}
