<?php
abstract class Mollie_WC_Gateway_AbstractSepaRecurring extends Mollie_WC_Gateway_AbstractSubscription
{


    /**
     * Mollie_WC_Gateway_AbstractSepaRecurring constructor.
     */
    public function __construct ()
    {
        parent::__construct();
        $directDebit = new Mollie_WC_Gateway_DirectDebit();
        if ($directDebit->is_available()) {
            $this->initSubscriptionSupport();
        }
        return $this;
    }

    /**
     * @param $renewal_order
     * @param $initial_order_status
     * @param $payment
     */
    protected function _updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment)
    {
        $this->updateOrderStatus(
            $renewal_order,
            self::STATUS_COMPLETED,
            __('Awaiting payment confirmation. For 10 Days', 'mollie-payments-for-woocommerce') . "\n"
        );

        $renewal_order->add_order_note(sprintf(
        /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%s payment started (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));

        $this->addPendingPaymentOrder($renewal_order);
    }

    /**
     * @param $renewal_order
     */
    protected function addPendingPaymentOrder($renewal_order)
    {
        global $wpdb;

        $confirmationDate = new DateTime();
        $confirmationDate->add(new DateInterval('P10D'));

        $wpdb->insert(
            $wpdb->mollie_pending_payment,
            array(
                'post_id' => $renewal_order->id,
                'expired_time' => $confirmationDate->getTimestamp(),
            )
        );
    }

    /**
     * @param $order
     */
    protected function deleteOrderFromPendingPaymentQueue($order)
    {
        global $wpdb;
        $wpdb->delete(
            $wpdb->mollie_pending_payment,
            array(
                'post_id' => $order->id,
            )
        );
    }


    /**
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookPaid (WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ' called.');

        // Woocommerce 2.2.0 has the option to store the Payment transaction id.
        $woo_version = get_option('woocommerce_version', 'Unknown');

        if (version_compare($woo_version, '2.2.0', '>='))
        {
            $order->payment_complete($payment->id);
        }
        else
        {
            $order->payment_complete();
        }

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));

        $this->deleteOrderFromPendingPaymentQueue($order);
    }

    /**
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookCancelled (WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ' called.');

        // Unset active Mollie payment id
        Mollie_WC_Plugin::getDataHelper()
            ->unsetActiveMolliePayment($order->id)
            ->setCancelledMolliePaymentId($order->id, $payment->id);

        // New order status
        $new_order_status = self::STATUS_PENDING;

        // Overwrite plugin-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled', $new_order_status);

        // Overwrite gateway-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled_' . $this->id, $new_order_status);

        // Reset state
        $this->updateOrderStatus($order, $new_order_status);

        // User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment cancelled (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));

        $this->deleteOrderFromPendingPaymentQueue($order);
    }

    /**
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookExpired (WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ' called.');

        // New order status
        $new_order_status = self::STATUS_CANCELLED;

        // Overwrite plugin-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired', $new_order_status);

        // Overwrite gateway-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired_' . $this->id, $new_order_status);

        // Cancel order
        $this->updateOrderStatus($order, $new_order_status);

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment expired (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
        $this->deleteOrderFromPendingPaymentQueue($order);
    }



}
