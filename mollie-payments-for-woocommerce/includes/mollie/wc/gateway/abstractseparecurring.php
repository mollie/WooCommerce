<?php
abstract class Mollie_WC_Gateway_AbstractSepaRecurring extends Mollie_WC_Gateway_AbstractSubscription
{

    const WAITING_CONFIRMATION_PERIOD = 'P15D';

    protected $recurringMethodId = '';


    /**
     * Mollie_WC_Gateway_AbstractSepaRecurring constructor.
     */
    public function __construct ()
    {
        parent::__construct();
        $directDebit = new Mollie_WC_Gateway_DirectDebit();
        if ($directDebit->is_available()) {
            $this->initSubscriptionSupport();
            $this->recurringMollieMethod = $directDebit;
        }
        return $this;
    }

    public function webhookAction ()
    {
        // Webhook test by Mollie
        if (isset($_GET['testByMollie']))
        {
            Mollie_WC_Plugin::debug(__METHOD__ . ': Webhook tested by Mollie.', true);
            return;
        }

        if (empty($_GET['order_id']) || empty($_GET['key']))
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ":  No order ID or order key provided.");
            return;
        }

        $order_id    = $_GET['order_id'];
        $key         = $_GET['key'];

        $data_helper = Mollie_WC_Plugin::getDataHelper();
        $order       = $data_helper->getWcOrder($order_id);

        if (!$order)
        {
            Mollie_WC_Plugin::setHttpResponseCode(404);
            Mollie_WC_Plugin::debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key))
        {
            Mollie_WC_Plugin::setHttpResponseCode(401);
            Mollie_WC_Plugin::debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }

        // No Mollie payment id provided
        if (empty($_REQUEST['id']))
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ': No payment ID provided.', true);
            return;
        }

        $payment_id = $_REQUEST['id'];
        $test_mode  = $data_helper->getActiveMolliePaymentMode($order_id) == 'test';

        // Load the payment from Mollie, do not use cache
        $payment = $data_helper->getPayment($payment_id, $test_mode, $use_cache = false);

        // Payment not found
        if (!$payment)
        {
            Mollie_WC_Plugin::setHttpResponseCode(404);
            Mollie_WC_Plugin::debug(__METHOD__ . ": payment $payment_id not found.", true);
            return;
        }

        if ($order_id != $payment->metadata->order_id)
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id");
            return;
        }

        // Payment requires different gateway, payment method changed on Mollie platform?
        $isValidPaymentMethod = in_array($payment->method,[$this->getMollieMethodId(),$this->getRecurringMollieMethodId()]);
        if (!$isValidPaymentMethod)
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug($this->id . ": Invalid gateway. This gateways can process Mollie " . $this->getMollieMethodId() . " payments. This payment has payment method " . $payment->method, true);
            return;
        }

        // Order does not need a payment
        if (!$this->orderNeedsPayment($order))
        {
            $paymentMethodTitle = $this->method_title;
            if ($payment->method == $this->getRecurringMollieMethodId()){
                $paymentMethodTitle = $this->getRecurringMollieMethodTitle();
            }

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));

            $this->deleteOrderFromPendingPaymentQueue($order);
            return;
        }

        Mollie_WC_Plugin::debug($this->id . ": Mollie payment {$payment->id} (" . $payment->mode . ") webhook call for order {$order->id}.", true);

        $method_name = 'onWebhook' . ucfirst($payment->status);

        if (method_exists($this, $method_name))
        {
            $this->{$method_name}($order, $payment);
        }
        else
        {
            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment status, placeholder 3: payment ID */
                __('%s payment %s (%s).', 'mollie-payments-for-woocommerce'),
                $this->method_title,
                $payment->status,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));
        }

        // Status 200
    }

    protected function getRecurringMollieMethodId()
    {
        return $this->recurringMollieMethod->getMollieMethodId();
    }

    protected function getRecurringMollieMethodTitle()
    {
        return $this->recurringMollieMethod->getDefaultTitle();
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

        $paymentMethodTitle = $this->method_title;
        if ($payment->method == $this->getRecurringMollieMethodId()){
            $paymentMethodTitle = $this->getRecurringMollieMethodTitle();
        }

        $renewal_order->add_order_note(sprintf(
        /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%s payment started (%s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
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
        $confirmationDate->add(new DateInterval(self::WAITING_CONFIRMATION_PERIOD));

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

        $paymentMethodTitle = $this->method_title;
        if ($payment->method == $this->getRecurringMollieMethodId()){
            $paymentMethodTitle = $this->getRecurringMollieMethodTitle();
        }

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
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

        $paymentMethodTitle = $this->method_title;
        if ($payment->method == $this->getRecurringMollieMethodId()){
            $paymentMethodTitle = $this->getRecurringMollieMethodTitle();
        }

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment expired (%s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
        $this->deleteOrderFromPendingPaymentQueue($order);
    }



}
