<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Plugin;

class MollieOrderService
{

    protected $gateway;
    /**
     * MollieOrderService constructor.
     */
    public function __construct()
    {
    }

    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }

    public function onWebhookAction()
    {
        // Webhook test by Mollie
        if (isset($_GET['testByMollie'])) {
            Plugin::debug(__METHOD__ . ': Webhook tested by MollieSettingsPage.', true);
            return;
        }

        if (empty($_GET['order_id']) || empty($_GET['key'])) {
            Plugin::setHttpResponseCode(400);
            Plugin::debug(__METHOD__ . ":  No order ID or order key provided.");
            return;
        }

        $order_id = sanitize_text_field($_GET['order_id']);
        $key = sanitize_text_field($_GET['key']);

        $data_helper = Plugin::getDataHelper();
        $order = wc_get_order($order_id);

        if (!$order) {
            Plugin::setHttpResponseCode(404);
            Plugin::debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key)) {
            Plugin::setHttpResponseCode(401);
            Plugin::debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }

        // No MollieSettingsPage payment id provided
        if (empty($_POST['id'])) {
            Plugin::setHttpResponseCode(400);
            Plugin::debug(__METHOD__ . ': No payment object ID provided.', true);
            return;
        }

        $payment_object_id = sanitize_text_field($_POST['id']);
        $test_mode = $data_helper->getActiveMolliePaymentMode($order_id) == 'test';

        // Load the payment from Mollie, do not use cache
        try {
            $payment_object = Plugin::getPaymentFactoryHelper()->getPaymentObject(
                $payment_object_id
            );
        } catch (ApiException $exception) {
            Plugin::setHttpResponseCode(400);
            Plugin::debug($exception->getMessage());
            return;
        }

        $payment = $payment_object->getPaymentObject($payment_object->data, $test_mode, $use_cache = false);

        // Payment not found
        if (!$payment) {
            Plugin::setHttpResponseCode(404);
            Plugin::debug(__METHOD__ . ": payment object $payment_object_id not found.", true);
            return;
        }

        if ($order_id != $payment->metadata->order_id) {
            Plugin::setHttpResponseCode(400);
            Plugin::debug(__METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id");
            return;
        }

        // Log a message that webhook was called, doesn't mean the payment is actually processed
        Plugin::debug($this->gateway->id . ": MollieSettingsPage payment object {$payment->id} (" . $payment->mode . ") webhook call for order {$order->get_id()}.", true);

        // Order does not need a payment
        if (! $this->orderNeedsPayment($order)) {
            // TODO David: move to payment object?
            // Add a debug message that order was already paid for
            $this->gateway->handlePaidOrderWebhook($order, $payment);

            // Check and process a possible refund or chargeback
            $this->processRefunds($order, $payment);
            $this->processChargebacks($order, $payment);

            return;
        }

        if ($payment->method == 'paypal' && $payment->billingAddress) {
            $this->setBillingAddressAfterPayment($payment, $order);
        }

        // Get payment method title
        $payment_method_title = $this->getPaymentMethodTitle($payment);

        // Create the method name based on the payment status
        $method_name = 'onWebhook' . ucfirst($payment->status);

        if (method_exists($payment_object, $method_name)) {
            $payment_object->{$method_name}($order, $payment, $payment_method_title);
        } else {
            $order->add_order_note(sprintf(
                                   /* translators: Placeholder 1: payment method title, placeholder 2: payment status, placeholder 3: payment ID */
                __('%1$s payment %2$s (%3$s), not processed.', 'mollie-payments-for-woocommerce'),
                $this->gateway->method_title,
                $payment->status,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));
        }

        // Status 200
    }
    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function orderNeedsPayment(WC_Order $order)
    {
        $order_id = $order->get_id();

        // Check whether the order is processed and paid via another gateway
        if ($this->isOrderPaidByOtherGateway($order)) {
            Plugin::debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: no, previously processed by other (non-Mollie) gateway.', true);

            return false;
        }

        // Check whether the order is processed and paid via MollieSettingsPage
        if (! $this->isOrderPaidAndProcessed($order)) {
            Plugin::debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, order not previously processed by Mollie gateway.', true);

            return true;
        }

        if ($order->needs_payment()) {
            Plugin::debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, WooCommerce thinks order needs payment.', true);

            return true;
        }

        // Has initial order status 'on-hold'
        if ($this->gateway->getInitialOrderStatus() === self::STATUS_ON_HOLD && $order->has_status(self::STATUS_ON_HOLD)) {
            Plugin::debug(__METHOD__ . ' ' . $this->gateway->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, has status On-Hold. ', true);

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
        Plugin::debug(__METHOD__ . " called for {$logId}");

        // Make sure there are refunds to process at all
        if (empty($payment->_links->refunds)) {
            Plugin::debug(
                __METHOD__ . ": No refunds to process for {$logId}",
                true
            );

            return;
        }

        // Check for new refund
        try {
            // Get all refunds for this payment
            $refunds = $payment->refunds();

            // Collect all refund IDs in one array
            $refundIds = [];
            foreach ($refunds as $refund) {
                $refundIds[] = $refund->id;
            }

            Plugin::debug(
                __METHOD__ . " All refund IDs for {$logId}: " . json_encode(
                    $refundIds
                )
            );

            // Get possibly already processed refunds
            if ($order->meta_exists('_mollie_processed_refund_ids')) {
                $processedRefundIds = $order->get_meta(
                    '_mollie_processed_refund_ids',
                    true
                );
            } else {
                $processedRefundIds = [];
            }

            Plugin::debug(
                __METHOD__ . " Already processed refunds for {$logId}: "
                . json_encode($processedRefundIds)
            );

            // Order the refund arrays by value (refund ID)
            asort($refundIds);
            asort($processedRefundIds);

            // Check if there are new refunds that need processing
            if ($refundIds != $processedRefundIds) {
                // There are new refunds.
                $refundsToProcess = array_diff($refundIds, $processedRefundIds);
                Plugin::debug(
                    __METHOD__
                    . " Refunds that need to be processed for {$logId}: "
                    . json_encode($refundsToProcess)
                );
            } else {
                // No new refunds, stop processing.
                Plugin::debug(
                    __METHOD__ . " No new refunds, stop processing for {$logId}"
                );

                return;
            }

            $dataHelper = Plugin::getDataHelper();
            $order = wc_get_order($orderId);

            foreach ($refundsToProcess as $refundToProcess) {
                Plugin::debug(
                    __METHOD__
                    . " New refund {$refundToProcess} processed in Mollie Dashboard for {$logId} Order note added, but order not updated."
                );

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
            Plugin::debug(
                __METHOD__ . " Updated, all processed refunds for {$logId}: "
                . json_encode($processedRefundIds)
            );

            $order->save();
            $this->processUpdateStateRefund($order, $payment);
            Plugin::debug(
                __METHOD__ . " Updated state for order {$orderId}"
            );

            do_action(
                Plugin::PLUGIN_ID . '_refunds_processed',
                $payment,
                $order
            );

            return;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            Plugin::debug(
                __FUNCTION__
                . " : Could not load refunds for {$payment->id}: {$e->getMessage()}"
                . ' (' . get_class($e) . ')'
            );
        }
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
        Plugin::debug(__METHOD__ . " called for {$logId}");

        // Make sure there are chargebacks to process at all
        if (empty($payment->_links->chargebacks)) {
            Plugin::debug(
                __METHOD__ . ": No chargebacks to process for {$logId}",
                true
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

            Plugin::debug(
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

            Plugin::debug(
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
                Plugin::debug(
                    __METHOD__
                    . " Chargebacks that need to be processed for {$logId}: "
                    . json_encode($chargebacksToProcess)
                );
            } else {
                // No new chargebacks, stop processing.
                Plugin::debug(
                    __METHOD__
                    . " No new chargebacks, stop processing for {$logId}"
                );

                return;
            }

            $dataHelper = Plugin::getDataHelper();
            $order = wc_get_order($orderId);

            // Update order notes, add message about chargeback
            foreach ($chargebacksToProcess as $chargebackToProcess) {
                Plugin::debug(
                    __METHOD__
                    . " New chargeback {$chargebackToProcess} for {$logId}. Order note and order status updated."
                );

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
            $newOrderStatus = AbstractGateway::STATUS_ON_HOLD;

            // Overwrite plugin-wide
            $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_on_hold', $newOrderStatus);

            // Overwrite gateway-wide
            $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . "_order_status_on_hold_{$this->gateway->id}", $newOrderStatus);

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
                    $payment->id . ($payment->mode == 'test' ? (' - ' . __(
                        'test mode',
                        'mollie-payments-for-woocommerce'
                    )) : '')
                ),
                $restoreStock = false
            );

            // Send a "Failed order" email to notify the admin
            $emails = WC()->mailer()->get_emails();
            if (!empty($emails) && !empty($orderId)
                && !empty($emails['WC_Email_Failed_Order'])
            ) {
                $emails['WC_Email_Failed_Order']->trigger($orderId);
            }

            $order->update_meta_data(
                '_mollie_processed_chargeback_ids',
                $processedChargebackIds
            );
            Plugin::debug(
                __METHOD__
                . " Updated, all processed chargebacks for {$logId}: "
                . json_encode($processedChargebackIds)
            );

            $order->save();

            //
            // Check if this is a renewal order, and if so set subscription to "On-Hold"
            //

            // Do extra checks if WooCommerce Subscriptions is installed
            if (class_exists('WC_Subscriptions')
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
                            $payment->id . ($payment->mode == 'test' ? (' - '
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
                Plugin::PLUGIN_ID . '_chargebacks_processed',
                $payment,
                $order
            );

            return;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            Plugin::debug(
                __FUNCTION__ . ": Could not load chargebacks for $payment->id: "
                . $e->getMessage() . ' (' . get_class($e) . ')'
            );
        }
    }

    /**
     * @param Payment|Order $payment
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
                AbstractGateway::STATUS_REFUNDED,
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
            Plugin::PLUGIN_ID . $refundType,
            $newOrderStatus
        );

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters(
            Plugin::PLUGIN_ID . $refundType . $this->gateway->id,
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
        // TODO David: this needs to be updated, doesn't work in all cases?
        $payment_method_title = '';
        if ($payment->method == $this->gateway->getMollieMethodId()) {
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
            case AbstractGateway::STATUS_ON_HOLD:
                if ($restore_stock == true) {
                    if (! $order->get_meta('_order_stock_reduced', true)) {
                        // Reduce order stock
                        wc_reduce_stock_levels($order->get_id());

                        Plugin::debug(__METHOD__ . ":  Stock for order {$order->get_id()} reduced.");
                    }
                }

                break;

            case AbstractGateway::STATUS_PENDING:
            case AbstractGateway::STATUS_FAILED:
            case AbstractGateway::STATUS_CANCELLED:
                if ($order->get_meta('_order_stock_reduced', true)) {
                    // Restore order stock
                    Plugin::getDataHelper()->restoreOrderStock($order);

                    Plugin::debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
                }

                break;
        }
    }
}
