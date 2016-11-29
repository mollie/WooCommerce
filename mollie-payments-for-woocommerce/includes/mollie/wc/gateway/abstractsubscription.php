<?php
abstract class Mollie_WC_Gateway_AbstractSubscription extends Mollie_WC_Gateway_Abstract
{

    protected $isSubscriptionPayment = false;
    /**
     * Mollie_WC_Gateway_AbstractSubscription constructor.
     */
    public function __construct ()
    {
        parent::__construct();


        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
            add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
            add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
            add_action( 'woocommerce_subscription_failing_payment_method_updated_mollie', array( $this, 'update_failing_payment_method' ), 10, 2 );

            add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
            add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
        }
    }

    /**
     *
     */
    protected function initSubscriptionSupport()
    {
        $supportSubscriptions = array(
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_cancellation',
            'subscription_date_changes',
            'multiple_subscriptions',
        );

        $this->supports = array_merge($this->supports,$supportSubscriptions);
    }


    /**
     * @param $order_id
     * @return array
     * @throws Mollie_WC_Exception_InvalidApiKey
     */
    public function process_subscription_payment( $order_id ) {
        $this->isSubscriptionPayment = true;
        return parent::process_payment($order_id);
    }

    /**
     * @param $order
     * @param $customer_id
     * @return array
     */
    protected function getPaymentRequestData($order, $customer_id)
    {
        $paymentRequestData = parent::getPaymentRequestData($order, $customer_id);
        if ($this->isSubscriptionPayment){
            $paymentRequestData['recurringType'] = 'first';
        }
        return $paymentRequestData;
    }

    /**
     * @param $order
     * @param $customer_id
     * @return array
     */
    protected function getRecurringPaymentRequestData($order, $customer_id)
    {
        $settings_helper     = Mollie_WC_Plugin::getSettingsHelper();
        $payment_description = $settings_helper->getPaymentDescription();
        $payment_locale      = $settings_helper->getPaymentLocale();
        $mollie_method       = $this->getMollieMethodId();
        $selected_issuer     = $this->getSelectedIssuer();
        $return_url          = $this->getReturnUrl($order);
        $webhook_url         = $this->getWebhookUrl($order);

        $payment_description = strtr($payment_description, array(
            '{order_number}' => $order->get_order_number(),
            '{order_date}'   => date_i18n(wc_date_format(), strtotime($order->order_date)),
        ));

        $data = array_filter(array(
            'amount'          => $order->get_total(),
            'description'     => $payment_description,
            'redirectUrl'     => $return_url,
            'webhookUrl'      => $webhook_url,
            'method'          => $mollie_method,
            'issuer'          => $selected_issuer,
            'locale'          => $payment_locale,
            'metadata'        => array(
                'order_id' => $order->id,
            ),
            'recurringType'   => 'recurring',
            'customerId'      => $customer_id,
        ));
        return $data;
    }

    /**
     * @param $order
     * @param $payment
     */
    protected function saveMollieInfo($order, $payment)
    {
        // Set active Mollie payment
        $this->setActiveMolliePayment($order->id, $payment);

        // Set Mollie customer
        $this->setUserMollieCustomerId($order->id, $payment->customerId);
    }

    /**
     * @param $amount_to_charge
     * @param $renewal_order
     * @return array
     * @throws Mollie_WC_Exception_InvalidApiKey
     */
    public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {


        if (!$renewal_order)
        {
            Mollie_WC_Plugin::debug($this->id . ': Could not process payment, order ' . $renewal_order->id . ' not found.');

            Mollie_WC_Plugin::addNotice(sprintf(__('Could not load order %s', 'mollie-payments-for-woocommerce'), $renewal_order->id), 'error');

            return array('result' => 'failure');
        }

        Mollie_WC_Plugin::debug($this->id . ': Try to create payment for order ' . $renewal_order->id);
        $initial_order_status = $this->getInitialOrderStatus();

        // Overwrite plugin-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status);

        // Overwrite gateway-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status);

        $customer_id    = $this->getOrderMollieCustomerId($renewal_order);

        $data = $this->getRecurringPaymentRequestData($renewal_order, $customer_id);

        $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $renewal_order);

        $settings_helper     = Mollie_WC_Plugin::getSettingsHelper();
        // Is test mode enabled?
        $test_mode = $settings_helper->isTestModeEnabled();
        try
        {
            Mollie_WC_Plugin::debug($this->id . ': Create payment for order ' . $renewal_order->id);

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_create_payment', $data, $renewal_order);
            $payment = null;
            // Create Mollie payment with customer id.
            try
            {
                Mollie_WC_Plugin::debug($this->id . ': Fetch mandate' . $renewal_order->id);
                $mandates =  Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->customers_mandates->withParentId($customer_id)->all();
                $validMandate = false;
                foreach ($mandates as $mandate) {
                    if ($mandate->status == 'valid') {
                        $validMandate = true;
                        $data['method'] = $mandate->method;
                        break;
                    }
                }
                if ($validMandate){
                    Mollie_WC_Plugin::debug($this->id . ': Valid mandate ' . $renewal_order->id);
                    $payment = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->payments->create($data);
                } else {
                    Mollie_WC_Plugin::debug($this->id . 'Payment problem ' . $renewal_order->id);
                    throw new Mollie_API_Exception(__('Payment cannot be processed.', 'mollie-payments-for-woocommerce-mandate-problem'));
                }
            }
            catch (Mollie_API_Exception $e)
            {
                if ($e->getField() !== 'customerId')
                {
                    throw $e;
                }

                // Retry without customer id.
                unset($data['customerId']);
                $payment = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->payments->create($data);
            }
            Mollie_WC_Plugin::debug($this->id . ': Created payment for order ' . $renewal_order->id. ' payment json response '.json_encode($payment));
            Mollie_WC_Plugin::getDataHelper()->unsetActiveMolliePayment($renewal_order->id);
            // Set active Mollie payment
            Mollie_WC_Plugin::getDataHelper()->setActiveMolliePayment($renewal_order->id, $payment);

            // Set Mollie customer
            $this->setUserMollieCustomerId($renewal_order->id, $payment->customerId);

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_payment_created', $payment, $renewal_order);

            Mollie_WC_Plugin::debug($this->id . ': Payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $renewal_order->id);

            // Set initial status
            // Status is only updated if the new status is not the same as the default order status (pending)
            $this->_updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment);


            return array(
                'result'   => 'success',
            );
        }
        catch (Mollie_API_Exception $e)
        {
            Mollie_WC_Plugin::debug($this->id . ': Failed to create payment for order ' . $renewal_order->id . ': ' . $e->getMessage());

            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(__('Could not create %s payment.', 'mollie-payments-for-woocommerce'), $this->title);

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                $message .= ' ' . $e->getMessage();
            }
            $renewal_order->update_status( 'failed',$message );

        }

        return array('result' => 'failure');
    }

    /**
     * @param $order_id
     * @param Mollie_API_Object_Payment $payment
     * @return $this
     */
    public function setActiveMolliePayment ($order_id, Mollie_API_Object_Payment $payment)
    {
        add_post_meta($order_id, '_mollie_payment_id', $payment->id, $single = true);
        add_post_meta($order_id, '_mollie_payment_mode', $payment->mode, $single = true);

        delete_post_meta($order_id, '_mollie_cancelled_payment_id');

        if ($payment->customerId)
        {
            add_post_meta($order_id, '_mollie_customer_id', $payment->customerId, $single = true);
        }

        // Also store it on the subscriptions being purchased or paid for in the order
        if ( wcs_order_contains_subscription( $order_id) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order_id);
        } elseif ( wcs_order_contains_renewal( $order_id) ) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
        } else {
            $subscriptions = array();
        }

        foreach( $subscriptions as $subscription ) {
            $this->unsetActiveMolliePayment($subscription->id);
            delete_post_meta($subscription->id, '_mollie_customer_id');
            add_post_meta( $subscription->id, '_mollie_payment_id', $payment->id, $single = true );
            add_post_meta( $subscription->id, '_mollie_payment_mode', $payment->mode, $single = true );
            delete_post_meta($subscription->id, '_mollie_cancelled_payment_id');
            if ($payment->customerId)
            {
                add_post_meta($subscription->id, '_mollie_customer_id', $payment->customerId, $single = true);
            }
        }

        return $this;
    }

    /**
     * @param $order_id
     * @return $this
     */
    public function unsetActiveMolliePayment ($order_id)
    {
        delete_post_meta($order_id, '_mollie_payment_id');
        delete_post_meta($order_id, '_mollie_payment_mode');

        return $this;
    }

    /**
     * @param $orderId
     * @param $customer_id
     * @return $this
     */
    public function setUserMollieCustomerId ($orderId, $customer_id)
    {
        if (!empty($customer_id))
        {
            update_post_meta($orderId, '_mollie_customer_id', $customer_id);
        }

        return $this;
    }


    /**
     * @param $order
     * @param bool|false $test_mode
     * @return null|string
     */
    protected function getUserMollieCustomerId($order, $test_mode = false)
    {
        $user_id = $order->customer_user;
        if (empty($user_id)){
            return null;
        }

        $customer_id = null;
        try
        {
            $userdata = get_userdata($user_id);

            $customer = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->customers->create(array(
                'name'     => trim($userdata->user_nicename),
                'email'    => trim($userdata->user_email),
                'locale'   => trim($this->getCurrentLocale()),
                'metadata' => array('user_id' => $user_id),
            ));


            $customer_id = $customer->id;
        }
        catch (Exception $e)
        {
            Mollie_WC_Plugin::debug(
                __FUNCTION__ . ": Could not create customer $user_id (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')'
            );
        }

        return $customer_id;
    }

    /**
     * @return mixed
     */
    protected function getCurrentLocale()
    {
        return apply_filters('wpml_current_language', get_locale());
    }

    /**
     * @param $order
     * @return mixed
     */
    public function getOrderMollieCustomerId($order)
    {
        $customerId = get_post_meta( $order->id, '_mollie_customer_id', true );
        return $customerId;
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
            $initial_order_status,
            __('Awaiting payment confirmation.', 'mollie-payments-for-woocommerce') . "\n"
        );

        $renewal_order->add_order_note(sprintf(
        /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%s payment started (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
    }

    /**
     * @param $resubscribe_order
     */
    public function delete_resubscribe_meta( $resubscribe_order ) {
        $this->delete_renewal_meta( $resubscribe_order );
    }

    /**
     * @param $renewal_order
     * @return mixed
     */
    public function delete_renewal_meta( $renewal_order ) {
        delete_post_meta( $renewal_order->id, '_mollie_card_4_digits' );
        delete_post_meta( $renewal_order->id, '_mollie_payment_id' );
        delete_post_meta( $renewal_order->id, '_mollie_payment_mode' );
        delete_post_meta( $renewal_order->id, '_mollie_cancelled_payment_id' );
        return $renewal_order;
    }

    /**
     * @param $payment_meta
     * @param $subscription
     * @return mixed
     */
    public function add_subscription_payment_meta( $payment_meta, $subscription ) {
        $payment_meta[ $this->id ] = array(
            'post_meta' => array(
                '_mollie_payment_id' => array(
                    'value' => get_post_meta( $subscription->id, '_mollie_payment_id', true ),
                    'label' => 'Mollie Payment ID',
                ),
                '_mollie_payment_mode' => array(
                    'value' => get_post_meta( $subscription->id, '_mollie_payment_mode', true ),
                    'label' => 'Mollie Payment Mode',
                ),
                '_mollie_customer_id' => array(
                    'value' => get_post_meta( $subscription->id, '_mollie_customer_id', true ),
                    'label' => 'Mollie Customer ID',
                ),
            ),
        );
        return $payment_meta;
    }

    /**
     * @param $payment_method_id
     * @param $payment_meta
     * @throws Exception
     */
    public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
        if ( $this->id === $payment_method_id ) {

            if ( ! isset( $payment_meta['post_meta']['_mollie_customer_id']['value'] ) || empty( $payment_meta['post_meta']['_mollie_customer_id']['value'] ) ) {
                throw new Exception( 'A "_mollie_customer_id" value is required.' );
            }
        }
    }

    /**
     * @param $subscription
     * @param $renewal_order
     */
    public function update_failing_payment_method( $subscription, $renewal_order ) {
        update_post_meta( $subscription->id, '_mollie_customer_id', $renewal_order->mollie_customer_id );
        update_post_meta( $subscription->id, '_mollie_payment_id', $renewal_order->mollie_payment_id );
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment ($order_id)
    {
        $isSubscription = $this->is_subscription($order_id);
        if ($isSubscription){
            $result = $this->process_subscription_payment($order_id);
            return $result;
        }

        $result = parent::process_payment($order_id);
        return $result;

    }


    /**
     * @param $order_id
     * @return bool
     */
    protected function is_subscription( $order_id ) {
        return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
    }

}
