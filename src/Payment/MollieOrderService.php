<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use WC_Order;

class MollieOrderService
{
    protected $gateway;
    /**
     * @var HttpResponse
     */
    private $httpResponse;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    /**
     * @var Data
     */
    protected $data;
    protected $pluginId;

    /**
     * PaymentService constructor.
     */
    public function __construct(
        HttpResponse $httpResponse,
        Logger $logger,
        PaymentFactory $paymentFactory,
        Data $data,
        string $pluginId
    ) {

        $this->httpResponse = $httpResponse;
        $this->logger = $logger;
        $this->paymentFactory = $paymentFactory;
        $this->data = $data;
        $this->pluginId = $pluginId;
    }

    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }

    public function onWebhookAction()
    {
        // Webhook test by Mollie
        if (isset($_GET['testByMollie'])) {
            $this->logger->debug(__METHOD__ . ': Webhook tested by Mollie.', [true]);
            return;
        }

        if (empty($_GET['order_id']) || empty($_GET['key'])) {
            $this->httpResponse->setHttpResponseCode(400);
            $this->logger->debug(__METHOD__ . ":  No order ID or order key provided.");
            return;
        }

        $order_id = sanitize_text_field(wp_unslash($_GET['order_id']));
        $key = sanitize_text_field(wp_unslash($_GET['key']));

        $data_helper = $this->data;
        $order = wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            $this->httpResponse->setHttpResponseCode(404);
            $this->logger->debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key)) {
            $this->httpResponse->setHttpResponseCode(401);
            $this->logger->debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway instanceof MolliePaymentGateway) {
            return;
        }
        $this->setGateway($gateway);
        // No Mollie payment id provided
        $paymentId = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($paymentId)) {
            $this->httpResponse->setHttpResponseCode(400);
            $this->logger->debug(__METHOD__ . ': No payment object ID provided.', [true]);
            return;
        }

        $payment_object_id = sanitize_text_field(wp_unslash($paymentId));
        $test_mode = $data_helper->getActiveMolliePaymentMode($order_id) === 'test';

        // Load the payment from Mollie, do not use cache
        try {
            $payment_object = $this->paymentFactory->getPaymentObject(
                $payment_object_id
            );
        } catch (ApiException $exception) {
            $this->httpResponse->setHttpResponseCode(400);
            $this->logger->debug($exception->getMessage());
            return;
        }

        $payment = $payment_object->getPaymentObject($payment_object->data(), $test_mode, $use_cache = false);

        // Payment not found
        if (!$payment) {
            $this->httpResponse->setHttpResponseCode(404);
            $this->logger->debug(__METHOD__ . ": payment object $payment_object_id not found.", [true]);
            return;
        }

        if ($order_id != $payment->metadata->order_id) {
            $this->httpResponse->setHttpResponseCode(400);
            $this->logger->debug(__METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id");
            return;
        }

        // Log a message that webhook was called, doesn't mean the payment is actually processed
        $this->logger->debug($this->gateway->id . ": Mollie payment object {$payment->id} (" . $payment->mode . ") webhook call for order {$order->get_id()}.", [true]);
        // Get payment method title
        $payment_method_title = $this->getPaymentMethodTitle($payment);

        // Create the method name based on the payment status
        $method_name = 'onWebhook' . ucfirst($payment->status);
        // Order does not need a payment
        if (! $this->orderNeedsPayment($order)) {
            // TODO David: move to payment object?
            // Add a debug message that order was already paid for
            $this->gateway->handlePaidOrderWebhook($order, $payment);

            // Check and process a possible refund or chargeback
            $this->processRefunds($order, $payment);
            $this->processChargebacks($order, $payment);
            //if the order gets updated to completed at mollie, we need to update the order status
            if ($order->get_status() === 'processing' && $payment->isCompleted() && method_exists($payment_object, 'onWebhookCompleted')) {
                $payment_object->onWebhookCompleted($order, $payment, $payment_method_title);
            }
            return;
        }

        if ($payment->method === 'paypal' && isset($payment->billingAddress) && $this->isOrderButtonPayment($order)) {
            $this->logger->debug($this->gateway->id . ": updating address from express button", [true]);
            $this->setBillingAddressAfterPayment($payment, $order);
        }

        if (method_exists($payment_object, $method_name)) {
            $payment_object->{$method_name}($order, $payment, $payment_method_title);
        } else {
            $order->add_order_note(sprintf(
                                   /* translators: Placeholder 1: payment method title, placeholder 2: payment status, placeholder 3: payment ID */
                __('%1$s payment %2$s (%3$s), not processed.', 'mollie-payments-for-woocommerce'),
                $this->gateway->method_title,
                $payment->status,
                $payment->id . ($payment->mode === 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));
        }

        do_action($this->pluginId . '_after_webhook_action', $payment, $order);
        // Status 200
    }
    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    public function orderNeedsPayment(WC_Order $order)
    {
        $order_id = $order->get_id();

        // Check whether the order is processed and paid via another gateway
        if ($this->isOrderPaidByOtherGateway($order)) {
            $this->logger->debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: no, previously processed by other (non-Mollie) gateway.', [true]);

            return false;
        }

        // Check whether the order is processed and paid via Mollie
        if (! $this->isOrderPaidAndProcessed($order)) {
            $this->logger->debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, order not previously processed by Mollie gateway.', [true]);

            return true;
        }

        if ($order->needs_payment()) {
            $this->logger->debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, WooCommerce thinks order needs payment.', [true]);

            return true;
        }

        // Has initial order status 'on-hold'
        if (
            $this->gateway->paymentMethod()->getInitialOrderStatus() === SharedDataDictionary::STATUS_ON_HOLD
            && $order->has_status(SharedDataDictionary::STATUS_ON_HOLD)
        ) {
            $this->logger->debug(
                __METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, has status On-Hold. ',
                [true]
            );
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    protected function isOrderPaidAndProcessed(WC_Order $order)
    {
        return $order->get_meta('_mollie_paid_and_processed', true);
    }

    /**
     * @return bool
     */
    protected function isOrderPaidByOtherGateway(WC_Order $order)
    {
        return $order->get_meta('_mollie_paid_by_other_gateway', true);
    }

    /**
     * @param WC_Order  $order
     * @param Payment|Order $payment
     */
    protected function processRefunds(WC_Order $order, $payment)
    {
        $orderId = $order->get_id();

        // Debug log ID (order id/payment id)
        $logId = "order {$orderId} / payment{$payment->id}";

        // Add message to log
        $this->logger->debug(__METHOD__ . " called for {$logId}");
        $hasLineRefund = $this->hasLineRefund($payment);

        // Make sure there are refunds to process at all
        if (empty($payment->_links->refunds) && !$hasLineRefund) {
            $this->logger->debug(
                __METHOD__ . ": No refunds to process for {$logId}",
                [true]
            );

            return;
        }

        $refundIds = $this->findRefundIds($payment);
        // Check for new refund
        $this->logger->debug(
            __METHOD__ . " All refund IDs for {$logId}: " . json_encode(
                $refundIds
            )
        );

        // Get possibly already processed refunds
        $processedRefundIds = $this->getProcessedRefundIds($order, $logId);

        // Order the refund arrays by value (refund ID)
        asort($refundIds);
        asort($processedRefundIds);

        // Check if no new refunds need processing return
        if ($refundIds === $processedRefundIds) {
            $this->logger->debug(
                __METHOD__ . " No new refunds, stop processing for {$logId}"
            );
            return;
        }
        // There are new refunds.
        $refundsToProcess = array_diff($refundIds, $processedRefundIds);
        $this->logger->debug(
            __METHOD__
            . " Refunds that need to be processed for {$logId}: "
            . json_encode($refundsToProcess)
        );
        $order = wc_get_order($orderId);

        $this->notifyProcessedRefunds($refundsToProcess, $logId, $order, $processedRefundIds);

        $order->save();
        $this->processUpdateStateRefund($order, $payment);
        $this->logger->debug(
            __METHOD__ . " Updated state for order {$orderId}"
        );

        do_action(
            $this->pluginId . '_refunds_processed',
            $payment,
            $order
        );
    }

    /**
     * @param WC_Order                                                $order
     * @param Payment|Order $payment
     */
    protected function processChargebacks(WC_Order $order, $payment)
    {
        $orderId = $order->get_id();

        // Debug log ID (order id/payment id)
        $logId = "order {$orderId} / payment {$payment->id}";

        // Add message to log
        $this->logger->debug(__METHOD__ . " called for {$logId}");

        // Make sure there are chargebacks to process at all
        if (empty($payment->_links->chargebacks)) {
            $this->logger->debug(
                __METHOD__ . ": No chargebacks to process for {$logId}",
                [true]
            );

            return;
        }

        // Check for new chargeback
        try {
            // Get all chargebacks for this payment
            $chargebacks = $payment->chargebacks();

            // Collect all chargeback IDs in one array
            $chargebackIds = [];
            foreach ($chargebacks as $chargeback) {
                $chargebackIds[] = $chargeback->id;
            }

            $this->logger->debug(
                __METHOD__ . " All chargeback IDs for {$logId}: " . json_encode(
                    $chargebackIds
                )
            );

            // Get possibly already processed chargebacks
            if ($order->meta_exists('_mollie_processed_chargeback_ids')) {
                $processedChargebackIds = $order->get_meta(
                    '_mollie_processed_chargeback_ids',
                    true
                );
            } else {
                $processedChargebackIds = [];
            }

            $this->logger->debug(
                __METHOD__ . " Already processed chargebacks for {$logId}: "
                . json_encode($processedChargebackIds)
            );

            // Order the chargeback arrays by value (chargeback ID)
            asort($chargebackIds);
            asort($processedChargebackIds);

            // Check if there are new chargebacks that need processing
            if ($chargebackIds != $processedChargebackIds) {
                // There are new chargebacks.
                $chargebacksToProcess = array_diff(
                    $chargebackIds,
                    $processedChargebackIds
                );
                $this->logger->debug(
                    __METHOD__
                    . " Chargebacks that need to be processed for {$logId}: "
                    . json_encode($chargebacksToProcess)
                );
            } else {
                // No new chargebacks, stop processing.
                $this->logger->debug(
                    __METHOD__
                    . " No new chargebacks, stop processing for {$logId}"
                );

                return;
            }

            $order = wc_get_order($orderId);

            // Update order notes, add message about chargeback
            foreach ($chargebacksToProcess as $chargebackToProcess) {
                $this->logger->debug(
                    __METHOD__
                    . " New chargeback {$chargebackToProcess} for {$logId}. Order note and order status updated."
                );
                /* translators: Placeholder 1: Chargeback to process id. */
                $order->add_order_note(
                    sprintf(
                        __(
                            'New chargeback %s processed! Order note and order status updated.',
                            'mollie-payments-for-woocommerce'
                        ),
                        $chargebackToProcess
                    )
                );

                $processedChargebackIds[] = $chargebackToProcess;
            }

            //
            // Update order status and add general note
            //

            // New order status
            $newOrderStatus = SharedDataDictionary::STATUS_ON_HOLD;

            // Overwrite plugin-wide
            $newOrderStatus = apply_filters($this->pluginId . '_order_status_on_hold', $newOrderStatus);

            // Overwrite gateway-wide
            $newOrderStatus = apply_filters($this->pluginId . "_order_status_on_hold_{$this->gateway->id}", $newOrderStatus);

            $paymentMethodTitle = $this->getPaymentMethodTitle($payment);

            // Update order status for order with charged_back payment, don't restore stock
            $this->updateOrderStatus(
                $order,
                $newOrderStatus,
                sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __(
                        '%1$s payment charged back via Mollie (%2$s). You will need to manually review the payment (and adjust product stocks if you use it).',
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

            // Send a "Failed order" email to notify the admin
            $emails = WC()->mailer()->get_emails();
            if (
                !empty($emails) && !empty($orderId)
                && !empty($emails['WC_Email_Failed_Order'])
            ) {
                $emails['WC_Email_Failed_Order']->trigger($orderId);
            }

            $order->update_meta_data(
                '_mollie_processed_chargeback_ids',
                $processedChargebackIds
            );
            $this->logger->debug(
                __METHOD__
                . " Updated, all processed chargebacks for {$logId}: "
                . json_encode($processedChargebackIds)
            );

            $order->save();

            //
            // Check if this is a renewal order, and if so set subscription to "On-Hold"
            //

            // Do extra checks if WooCommerce Subscriptions is installed
            if (
                class_exists('WC_Subscriptions')
                && class_exists(
                    'WC_Subscriptions_Admin'
                )
            ) {
                // Also store it on the subscriptions being purchased or paid for in the order
                if (wcs_order_contains_subscription($orderId)) {
                    $subscriptions = wcs_get_subscriptions_for_order($orderId);
                } elseif (wcs_order_contains_renewal($orderId)) {
                    $subscriptions = wcs_get_subscriptions_for_renewal_order(
                        $orderId
                    );
                } else {
                    $subscriptions = [];
                }

                foreach ($subscriptions as $subscription) {
                    $this->updateOrderStatus(
                        $subscription,
                        $newOrderStatus,
                        sprintf(
                        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                            __(
                                '%1$s payment charged back via Mollie (%2$s). Subscription status updated, please review (and adjust product stocks if you use it).',
                                'mollie-payments-for-woocommerce'
                            ),
                            $paymentMethodTitle,
                            $payment->id . ($payment->mode === 'test' ? (' - '
                                . __(
                                    'test mode',
                                    'mollie-payments-for-woocommerce'
                                )) : '')
                        ),
                        $restoreStock = false
                    );
                }
            }

            do_action(
                $this->pluginId . '_chargebacks_processed',
                $payment,
                $order
            );

            return;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            $this->logger->debug(
                __FUNCTION__ . ": Could not load chargebacks for $payment->id: "
                . $e->getMessage() . ' (' . get_class($e) . ')'
            );
        }
    }

    /**
     * Check if there is a refund inside an order line
     *
     * @param $payment
     * @return bool
     */
    protected function hasLineRefund($payment): bool
    {
        return !empty($payment->_embedded->refunds);
    }

    /**
     * Find the Ids of the refunds
     *
     * @param $payment
     * @return array
     */
    protected function findRefundIds($payment): array
    {
        if (empty($payment->_links->refunds)) {
            return $this->findRefundIdsByLine($payment);
        }
        return $this->findRefundIdsByLinks($payment);
    }

    /**
     * Find refund ids inside an order line
     *
     * @param $payment
     * @return array
     */
    protected function findRefundIdsByLine($payment): array
    {
        return array_map(static function ($refund) {
            return $refund->id;
        }, $payment->_embedded->refunds);
    }

    /**
     * calculate refund amount inside order lines
     *
     * @param $payment
     * @return float
     */
    protected function calculateRefundByLine($payment): float
    {
        $refundAmount = 0.0;
        $refunds = $payment->_embedded->refunds;
        foreach ($refunds as $refund) {
            $refundAmount += (float) $refund->amount->value;
        }
        return $refundAmount;
    }

    /**
     * Check if there is a refund inside an order line
     *
     * @param $payment
     * @return array
     */
    protected function findRefundIdsByLinks($payment): array
    {
        $refundIds = [];
        try {
            // Get all refunds for this payment
            $refunds = $payment->refunds();
            foreach ($refunds as $refund) {
                $refundIds[] = $refund->id;
            }
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            $this->logger->debug(
                __FUNCTION__
                . " : Could not load refunds for {$payment->id}: {$e->getMessage()}"
                . ' (' . get_class($e) . ')'
            );
        }
        return $refundIds;
    }

    /**
     * @param Order $payment
     * @param WC_Order $order
     */
    protected function setBillingAddressAfterPayment($payment, $order)
    {
        $billingAddress = $payment->billingAddress;
        $wooBillingAddress = [
            'first_name' => $billingAddress->givenName,
            'last_name' => $billingAddress->familyName,
            'email' => $billingAddress->email,
            'phone' => null,
            'address_1' => $billingAddress->streetAndNumber,
            'address_2' => null,
            'city' => $billingAddress->city,
            'state' => null,
            'postcode' => $billingAddress->postalCode,
            'country' => $billingAddress->country,
        ];
        $order->set_address($wooBillingAddress, 'billing');
    }

    /**
     * @param Payment|Order $payment
     *
     * @return bool
     */
    protected function isPartialRefund($payment)
    {
        if ($payment->amountRefunded === null) {
            return (float)($payment->amount->value - $this->calculateRefundByLine($payment)) !== 0.0;
        }
        return (float)($payment->amount->value - $payment->amountRefunded->value) !== 0.0;
    }

    /**
     * @param WC_Order                                                $order
     * @param Payment|Order $payment
     */
    protected function processUpdateStateRefund(WC_Order $order, $payment)
    {
        if (!$this->isPartialRefund($payment)) {
            $this->updateStateRefund(
                $order,
                $payment,
                SharedDataDictionary::STATUS_REFUNDED,
                '_order_status_refunded'
            );
        }
    }

    /**
     * @param WC_Order                                                $order
     * @param Payment|Order $payment
     * @param                                                         $newOrderStatus
     * @param                                                         $refundType
     */
    protected function updateStateRefund(
        WC_Order $order,
        $payment,
        $newOrderStatus,
        $refundType
    ) {
        // Overwrite plugin-wide
        $newOrderStatus = apply_filters(
            $this->pluginId . $refundType,
            $newOrderStatus
        );

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters(
            $this->pluginId . $refundType . $this->gateway->id,
            $newOrderStatus
        );
        // New order status
        $note = $this->renderNote($payment, $refundType);
        $this->updateOrderStatus(
            $order,
            $newOrderStatus,
            $note,
            $restoreStock = false
        );
    }

    /**
     * @param $payment
     * @param $refundType
     *
     * @return string
     */
    protected function renderNote($payment, $refundType)
    {
        $paymentMethodTitle = $this->getPaymentMethodTitle($payment);
        $paymentTestModeNote = $this->paymentTestModeNote($payment);

        return sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __(
                '%1$s payment %2$s via Mollie (%3$s %4$s). You will need to manually review the payment (and adjust product stocks if you use it).',
                'mollie-payments-for-woocommerce'
            ),
            $paymentMethodTitle,
            $refundType,
            $payment->id,
            $paymentTestModeNote
        );
    }

    protected function paymentTestModeNote($payment)
    {
        $note = __('test mode', 'mollie-payments-for-woocommerce');
        $note = $payment->mode === 'test' ? " - {$note}" : '';

        return $note;
    }

    //refactor

    /**
     * @param $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {
        $payment_method_title = '';
        if (!($this->gateway instanceof MolliePaymentGateway)) {
            return $payment_method_title;
        }
        if ($payment->method === $this->gateway->paymentMethod()->getProperty('id')) {
            $payment_method_title = $this->gateway->method_title;
        }
        return $payment_method_title;
    }
    /**
     * @param \WC_Order $order
     * @param string $new_status
     * @param string $note
     * @param bool $restore_stock
     */
    public function updateOrderStatus(\WC_Order $order, $new_status, $note = '', $restore_stock = true)
    {
        $order->update_status($new_status, $note);

        switch ($new_status) {
            case SharedDataDictionary::STATUS_ON_HOLD:
                if ($restore_stock === true) {
                    if (! $order->get_meta('_order_stock_reduced', true)) {
                        // Reduce order stock
                        wc_reduce_stock_levels($order->get_id());

                        $this->logger->debug(__METHOD__ . ":  Stock for order {$order->get_id()} reduced.");
                    }
                }

                break;

            case SharedDataDictionary::STATUS_PENDING:
            case SharedDataDictionary::STATUS_FAILED:
            case SharedDataDictionary::STATUS_CANCELLED:
                if ($order->get_meta('_order_stock_reduced', true)) {
                    // Restore order stock
                    $this->data->restoreOrderStock($order);

                    $this->logger->debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
                }

                break;
        }
    }

    /**
     * @param WC_Order $order
     * @param string $logId
     * @return array|mixed|string|void
     */
    protected function getProcessedRefundIds(WC_Order $order, string $logId)
    {
        if ($order->meta_exists('_mollie_processed_refund_ids')) {
            $processedRefundIds = $order->get_meta(
                '_mollie_processed_refund_ids',
                true
            );
        } else {
            $processedRefundIds = [];
        }

        $this->logger->debug(
            __METHOD__ . " Already processed refunds for {$logId}: "
            . json_encode($processedRefundIds)
        );
        return $processedRefundIds;
    }

    /**
     * @param array $refundsToProcess
     * @param string $logId
     * @param $order
     * @param $processedRefundIds
     * @return mixed
     */
    protected function notifyProcessedRefunds(array $refundsToProcess, string $logId, $order, $processedRefundIds)
    {
        foreach ($refundsToProcess as $refundToProcess) {
            $this->logger->debug(
                __METHOD__
                . " New refund {$refundToProcess} processed in Mollie Dashboard for {$logId} Order note added, but order not updated."
            );
            /* translators: Placeholder 1: Refund to process id. */
            $order->add_order_note(
                sprintf(
                    __(
                        'New refund %s processed in Mollie Dashboard! Order note added, but order not updated.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $refundToProcess
                )
            );

            $processedRefundIds[] = $refundToProcess;
        }

        $order->update_meta_data(
            '_mollie_processed_refund_ids',
            $processedRefundIds
        );
        $this->logger->debug(
            __METHOD__ . " Updated, all processed refunds for {$logId}: "
            . json_encode($processedRefundIds)
        );
        return $processedRefundIds;
    }

    protected function isOrderButtonPayment(WC_Order $order): bool
    {
        return $order->get_meta('_mollie_payment_method_button') === 'PayPalButton';
    }
}
