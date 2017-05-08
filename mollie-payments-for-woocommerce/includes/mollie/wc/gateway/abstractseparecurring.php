<?php
abstract class Mollie_WC_Gateway_AbstractSepaRecurring extends Mollie_WC_Gateway_AbstractSubscription
{

    const WAITING_CONFIRMATION_PERIOD_DAYS = '15';

    protected $recurringMollieMethod = null;


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

    /**
     * @return string
     */
    protected function getRecurringMollieMethodId()
    {
        $result = null;
        if ($this->recurringMollieMethod){
            $result = $this->recurringMollieMethod->getMollieMethodId();
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getRecurringMollieMethodTitle()
    {
        $result = null;
        if ($this->recurringMollieMethod){
            $result = $this->recurringMollieMethod->getDefaultTitle();
        }

        return $result;
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
            __("Awaiting payment confirmation. For %s Days", 'mollie-payments-for-woocommerce') . "\n",
            self::WAITING_CONFIRMATION_PERIOD_DAYS
        );

        $paymentMethodTitle = $this->getPaymentMethodTitle($payment);

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
        $period = 'P'.self::WAITING_CONFIRMATION_PERIOD_DAYS . 'D';
        $confirmationDate->add(new DateInterval($period));

        $wpdb->insert(
            $wpdb->mollie_pending_payment,
            array(
                'post_id' => $renewal_order->get_id(),
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
                'post_id' => $order->get_id(),
            )
        );
    }

    /**
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookPaid(WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        parent::onWebhookPaid($order, $payment);
        if ($this->is_subscription($order->get_id())) {
            $this->deleteOrderFromPendingPaymentQueue($order);
        }
    }

    /**
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookCancelled(WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        parent::onWebhookCancelled($order, $payment);

        if ($this->is_subscription($order->get_id())) {
            $this->deleteOrderFromPendingPaymentQueue($order);
        }
    }

    /**
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookExpired(WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        parent::onWebhookExpired($order, $payment);

        if ($this->is_subscription($order->get_id())) {
            $this->deleteOrderFromPendingPaymentQueue($order);
        }
    }

    /**
     * @param null $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {
        $paymentMethodTitle = parent::getPaymentMethodTitle($payment);
        $orderId = $payment->metadata->order_id;
        if ($orderId && $this->is_subscription($orderId) && $payment->method == $this->getRecurringMollieMethodId()){
            $paymentMethodTitle = $this->getRecurringMollieMethodTitle();
        }

        return $paymentMethodTitle;
    }

    /**
     * @param $order
     * @param $payment
     */
    protected function handlePayedOrderWebhook($order, $payment)
    {
        // Duplicate webhook call
        if ($this->is_subscription($order->get_id()) && isset($payment->recurringType) && $payment->recurringType == 'recurring') {
            $paymentMethodTitle = $this->getPaymentMethodTitle($payment);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));

            $this->deleteOrderFromPendingPaymentQueue($order);
            return;
        }

        parent::handlePayedOrderWebhook($order, $payment);

    }

    /**
     * @param $payment
     * @return bool
     */
    protected function isValidPaymentMethod($payment)
    {
        $isValidPaymentMethod = parent::isValidPaymentMethod($payment);
        $orderId = $payment->metadata->order_id;

        // First payment was made by one gateway, and the next from another.
        // For Example Recurring First with IDEAL, the second With Sepa Direct Debit
        if ($orderId && $this->is_subscription($orderId)){
            $isValidPaymentMethod = in_array($payment->method, array($this->getMollieMethodId(),$this->getRecurringMollieMethodId()));
        }

        return $isValidPaymentMethod;
    }


}
