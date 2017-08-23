<?php
abstract class Mollie_WC_Gateway_AbstractSubscription extends Mollie_WC_Gateway_Abstract
{

    const PAYMENT_TEST_MODE = 'test';

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
	        'subscription_cancellation',
	        'subscription_suspension',
	        'subscription_reactivation',
	        'subscription_amount_changes',
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

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
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
	    } else {
		    $payment_description = strtr($payment_description, array(
			    '{order_number}' => $order->get_order_number(),
			    '{order_date}'   => date_i18n(wc_date_format(), $order->get_date_created()->getTimestamp()),
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
				    'order_id' => $order->get_id(),
			    ),
			    'recurringType'   => 'recurring',
			    'customerId'      => $customer_id,
		    ));
	    }

        return $data;
    }

    /**
     * @param $order
     * @param $payment
     */
    protected function saveMollieInfo($order, $payment)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    if ( ! $this->is_subscription( $order->id ) ) {
			    parent::saveMollieInfo( $order, $payment );

			    return;
		    }
		    // Set active Mollie payment
		    $this->setActiveMolliePayment( $order->id, $payment );

		    // Set Mollie customer
		    $this->setUserMollieCustomerId( $order->id, $payment->customerId );
	    } else {
		    if ( ! $this->is_subscription( $order->get_id() ) ) {
			    parent::saveMollieInfo( $order, $payment );

			    return;
		    }
		    // Set active Mollie payment
		    $this->setActiveMolliePayment( $order->get_id(), $payment );

		    // Set Mollie customer
		    $this->setUserMollieCustomerId( $order->get_id(), $payment->customerId );
	    }
    }

	/**
	 * @param $renewal_order
	 * @param $payment
	 *
	 * @return void
	 */
	public function update_subscription_status_for_direct_debit( $renewal_order, $payment ) {

		// Get renewal order id
		$renewal_order_id  = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $renewal_order->id : $renewal_order->get_id();

		// Make sure order is a renewal order with subscription
		if ( wcs_order_contains_renewal( $renewal_order_id ) ) {

			// Get required information about order and subscription
			$renewal_order     = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $renewal_order_id );
			$mollie_payment_id = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? get_post_meta( $renewal_order_id, '_mollie_payment_id', $single = true ) : $renewal_order->get_meta( '_mollie_payment_id', $single = true );
			$subscription_id   = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? get_post_meta( $renewal_order_id, '_subscription_renewal', $single = true ) : $renewal_order->get_meta( '_subscription_renewal', $single = true );
			$subscription      = wcs_get_subscription( $subscription_id );
			$current_method    = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? get_post_meta( $renewal_order_id, '_payment_method', $single = true ) : $subscription->get_payment_method();

			// Check that subscription status isn't already active
			if ( $subscription->get_status() == 'active' ) {
				return;
			}

			// Check that payment method is SEPA Direct Debit or similar
			$methods_needing_update = array (
				'mollie_wc_gateway_directdebit',
				'mollie_wc_gateway_ideal',
				'mollie_wc_gateway_mistercash',
				'mollie_wc_gateway_bancontact',
				'mollie_wc_gateway_sofort',
				'mollie_wc_gateway_kbc',
				'mollie_wc_gateway_belfius',
			);

			if ( in_array( $current_method, $methods_needing_update ) == false ) {
				return;
			}

			// Check that a new payment is made for renewal order
			if ( $mollie_payment_id == null ) {
				return;
			}

			// Update subscription to Active
			$subscription->update_status( 'active' );

			// Add order note to subscription explaining the change
			$subscription->add_order_note(
			/* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
				__( 'Updated subscription from \'On hold\' to \'Active\' until payment fails, because a SEPA Direct Debit payment takes some time to process.', 'mollie-payments-for-woocommerce' )
			);

		}
		return;

	}

    /**
     * @param $amount_to_charge
     * @param $renewal_order
     * @return array
     * @throws Mollie_WC_Exception_InvalidApiKey
     */
    public function scheduled_subscription_payment( $amount_to_charge, $renewal_order )
    {
        if (!$renewal_order)
        {
	        if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		        Mollie_WC_Plugin::debug($this->id . ': Could not process payment, order ' . $renewal_order->id . ' not found.');

		        Mollie_WC_Plugin::addNotice(sprintf(__('Could not load order %s', 'mollie-payments-for-woocommerce'), $renewal_order->id), 'error');
	        } else {
		        Mollie_WC_Plugin::debug($this->id . ': Could not process payment, order ' . $renewal_order->get_id() . ' not found.');

		        Mollie_WC_Plugin::addNotice(sprintf(__('Could not load order %s', 'mollie-payments-for-woocommerce'), $renewal_order->get_id()), 'error');
	        }

            return array('result' => 'failure');
        }

	    $renewal_order_id 	= ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $renewal_order->id : $renewal_order->get_id();

	    Mollie_WC_Plugin::debug($this->id . ': Try to create payment for order ' . $renewal_order_id);
        $initial_order_status = $this->getInitialOrderStatus();

        // Overwrite plugin-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status);

        // Overwrite gateway-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status);

        $customer_id    = $this->getOrderMollieCustomerId($renewal_order);

        $data = $this->getRecurringPaymentRequestData($renewal_order, $customer_id);

        $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $renewal_order);


        // Is test mode enabled?
        $test_mode = $this->isTestModeEnabledForRenewalOrder($renewal_order);
        try
        {
            Mollie_WC_Plugin::debug($this->id . ': Create payment for order ' . $renewal_order_id);

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_create_payment', $data, $renewal_order);
            $payment = null;
            // Create Mollie payment with customer id.
            try
            {
                Mollie_WC_Plugin::debug($this->id . ': Fetch mandate ' . $renewal_order_id);
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
                    Mollie_WC_Plugin::debug($this->id . ': Valid mandate ' . $renewal_order_id);
                    $payment = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->payments->create($data);
                } else {
                    Mollie_WC_Plugin::debug($this->id . 'Payment problem ' . $renewal_order_id);
                    throw new Mollie_API_Exception(__('Payment cannot be processed, no valid mandate.', 'mollie-payments-for-woocommerce-mandate-problem'));
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

	        // Update payment method to actual payment method used for renewal order, this is
	        // for subscriptions where the first order used methods like iDEAL as first payment and
	        // later renewal orders switch to SEPA Direct Debit.

	        $methods_needing_update = array (
		        'mollie_wc_gateway_ideal',
		        'mollie_wc_gateway_mistercash',
		        'mollie_wc_gateway_bancontact',
		        'mollie_wc_gateway_sofort',
		        'mollie_wc_gateway_kbc',
		        'mollie_wc_gateway_belfius',
	        );

	        $current_method = get_post_meta( $renewal_order_id, '_payment_method', $single = true );

	        if ( in_array( $current_method, $methods_needing_update ) && $payment->method == 'directdebit' ) {
		        if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			        update_post_meta( $renewal_order_id, '_payment_method', 'mollie_wc_gateway_directdebit' );
			        update_post_meta( $renewal_order_id, '_payment_method_title', 'SEPA Direct Debit' );
		        } else {
			        $renewal_order->update_meta_data( '_payment_method', 'mollie_wc_gateway_directdebit' );
			        $renewal_order->update_meta_data( '_payment_method_title', 'SEPA Direct Debit' );
			        $renewal_order->save();
		        }
	        }

            Mollie_WC_Plugin::debug($this->id . ': Created payment for order ' . $renewal_order_id. ' payment json response '.json_encode($payment));
            Mollie_WC_Plugin::getDataHelper()->unsetActiveMolliePayment($renewal_order_id);
            // Set active Mollie payment
            Mollie_WC_Plugin::getDataHelper()->setActiveMolliePayment($renewal_order_id, $payment);

            // Set Mollie customer
            $this->setUserMollieCustomerId($renewal_order_id, $payment->customerId);

            // Tell WooCommerce a new payment was created for the order/subscription
            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_payment_created', $payment, $renewal_order);

            Mollie_WC_Plugin::debug($this->id . ': Payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $renewal_order_id);

            // Set initial status
            // Status is only updated if the new status is not the same as the default order status (pending)
            $this->_updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment);

            // Update status of subscriptions with payment method SEPA Direct Debit or similar
	        $this->update_subscription_status_for_direct_debit( $renewal_order, $payment );

            return array(
                'result'   => 'success',
            );
        }
        catch (Mollie_API_Exception $e)
        {
            Mollie_WC_Plugin::debug($this->id . ': Failed to create payment for order ' . $renewal_order_id . ': ' . $e->getMessage());

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

    public function isTestModeEnabledForRenewalOrder($order)
    {
        $result = false;
        $subscriptions = array();
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    if ( wcs_order_contains_renewal( $order->id) ) {
			    $subscriptions = wcs_get_subscriptions_for_renewal_order( $order->id );
		    }

		    foreach( $subscriptions as $subscription ) {
			    $paymentMode = get_post_meta( $subscription->id, '_mollie_payment_mode', true );
			    if ($paymentMode == self::PAYMENT_TEST_MODE){
				    $result = true;
				    break;
			    }
		    }
	    } else {
		    if ( wcs_order_contains_renewal( $order->get_id()) ) {
			    $subscriptions = wcs_get_subscriptions_for_renewal_order( $order->get_id() );
		    }

		    foreach( $subscriptions as $subscription ) {
			    $paymentMode = $subscription->get_meta( '_mollie_payment_mode', true );
			    if ($paymentMode == self::PAYMENT_TEST_MODE){
				    $result = true;
				    break;
			    }
		    }
	    }

        return $result;
    }
    /**
     * @param $order_id
     * @param Mollie_API_Object_Payment $payment
     * @return $this
     */
    public function setActiveMolliePayment ($order_id, Mollie_API_Object_Payment $payment)
    {

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

		    add_post_meta( $order_id, '_mollie_payment_id', $payment->id, $single = true );
		    add_post_meta( $order_id, '_mollie_payment_mode', $payment->mode, $single = true );

		    delete_post_meta( $order_id, '_mollie_cancelled_payment_id' );

		    if ( $payment->customerId ) {
			    add_post_meta( $order_id, '_mollie_customer_id', $payment->customerId, $single = true );
		    }

		    // Also store it on the subscriptions being purchased or paid for in the order
		    if ( wcs_order_contains_subscription( $order_id ) ) {
			    $subscriptions = wcs_get_subscriptions_for_order( $order_id );
		    } elseif ( wcs_order_contains_renewal( $order_id ) ) {
			    $subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		    } else {
			    $subscriptions = array ();
		    }

		    foreach ( $subscriptions as $subscription ) {
			    $this->unsetActiveMolliePayment( $subscription->id );
			    delete_post_meta( $subscription->id, '_mollie_customer_id' );
			    add_post_meta( $subscription->id, '_mollie_payment_id', $payment->id, $single = true );
			    add_post_meta( $subscription->id, '_mollie_payment_mode', $payment->mode, $single = true );
			    delete_post_meta( $subscription->id, '_mollie_cancelled_payment_id' );
			    if ( $payment->customerId ) {
				    add_post_meta( $subscription->id, '_mollie_customer_id', $payment->customerId, $single = true );
			    }
		    }

	    } else {

		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

		    $order->update_meta_data( '_mollie_payment_id', $payment->id );
		    $order->update_meta_data( '_mollie_payment_mode', $payment->mode );

		    $order->delete_meta_data( '_mollie_cancelled_payment_id' );

		    if ( $payment->customerId ) {
			    $order->update_meta_data( '_mollie_customer_id', $payment->customerId );
		    }

		    // Also store it on the subscriptions being purchased or paid for in the order
		    if ( wcs_order_contains_subscription( $order_id ) ) {
			    $subscriptions = wcs_get_subscriptions_for_order( $order_id );
		    } elseif ( wcs_order_contains_renewal( $order_id ) ) {
			    $subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		    } else {
			    $subscriptions = array ();
		    }

		    foreach ( $subscriptions as $subscription ) {
			    $this->unsetActiveMolliePayment( $subscription->get_id() );
			    $subscription->delete_meta_data( '_mollie_customer_id' );
			    $subscription->update_meta_data( '_mollie_payment_id', $payment->id );
			    $subscription->update_meta_data( '_mollie_payment_mode', $payment->mode );
			    $subscription->delete_meta_data( '_mollie_cancelled_payment_id' );
			    if ( $payment->customerId ) {
				    $subscription->update_meta_data( '_mollie_customer_id', $payment->customerId );
			    }
			    $subscription->save();
		    }

		    $order->save();

	    }

        return $this;
    }

    /**
     * @param $order_id
     * @return $this
     */
    public function unsetActiveMolliePayment ($order_id)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    delete_post_meta($order_id, '_mollie_payment_id');
		    delete_post_meta($order_id, '_mollie_payment_mode');
	    } else {
		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
		    $order->delete_meta_data( '_mollie_payment_id' );
		    $order->delete_meta_data( '_mollie_payment_mode' );
		    $order->save();
	    }

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
	        if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		        update_post_meta( $orderId, '_mollie_customer_id', $customer_id );
	        } else {
		        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $orderId );
		        $order->update_meta_data( '_mollie_customer_id', $customer_id );
		        $order->save();
	        }
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
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $user_id = $order->customer_user;
	    } else {
		    $user_id = $order->get_customer_id();
	    }

        if (empty($user_id)){
            return null;
        }

        $customer_id = null;
        try
        {
            $userdata = get_userdata($user_id);

	        // Get the best name for use as Mollie Customer name
	        $user_full_name = $userdata->first_name . ' ' . $userdata->last_name;

	        if ( strlen( trim( $user_full_name ) ) == null ) {
		        $user_full_name = $userdata->display_name;
	        }

	        $customer = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers->create( array (
		        'name'     => trim( $user_full_name ),
		        'email'    => trim( $userdata->user_email ),
		        'locale'   => trim( $this->getCurrentLocale() ),
		        'metadata' => array ( 'user_id' => $user_id ),
	        ) );


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
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $customerId = get_post_meta( $order->id, '_mollie_customer_id', true );
	    } else {
		    $customerId = $order->get_meta( '_mollie_customer_id', true );
	    }

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
    public function delete_resubscribe_meta( $resubscribe_order )
    {
        $this->delete_renewal_meta( $resubscribe_order );
    }

    /**
     * @param $renewal_order
     * @return mixed
     */
    public function delete_renewal_meta( $renewal_order )
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    delete_post_meta( $renewal_order->id, '_mollie_card_4_digits' );
		    delete_post_meta( $renewal_order->id, '_mollie_payment_id' );
		    delete_post_meta( $renewal_order->id, '_mollie_payment_mode' );
		    delete_post_meta( $renewal_order->id, '_mollie_cancelled_payment_id' );
	    } else {
		    $renewal_order->delete_meta_data( '_mollie_card_4_digits' );
		    $renewal_order->delete_meta_data( '_mollie_payment_id' );
		    $renewal_order->delete_meta_data( '_mollie_payment_mode' );
		    $renewal_order->delete_meta_data( '_mollie_cancelled_payment_id' );
		    $renewal_order->save();
	    }

        return $renewal_order;
    }

    /**
     * @param $payment_meta
     * @param $subscription
     * @return mixed
     */
    public function add_subscription_payment_meta( $payment_meta, $subscription )
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $payment_meta[ $this->id ] = array (
			    'post_meta' => array (
				    '_mollie_payment_id'   => array (
					    'value' => get_post_meta( $subscription->id, '_mollie_payment_id', true ),
					    'label' => 'Mollie Payment ID',
				    ),
				    '_mollie_payment_mode' => array (
					    'value' => get_post_meta( $subscription->id, '_mollie_payment_mode', true ),
					    'label' => 'Mollie Payment Mode',
				    ),
				    '_mollie_customer_id'  => array (
					    'value' => get_post_meta( $subscription->id, '_mollie_customer_id', true ),
					    'label' => 'Mollie Customer ID',
				    ),
			    ),
		    );
	    } else {
		    $payment_meta[ $this->id ] = array (
			    'post_meta' => array (
				    '_mollie_payment_id'   => array (
					    'value' => $subscription->get_meta( '_mollie_payment_id', true ),
					    'label' => 'Mollie Payment ID',
				    ),
				    '_mollie_payment_mode' => array (
					    'value' => $subscription->get_meta( '_mollie_payment_mode', true ),
					    'label' => 'Mollie Payment Mode',
				    ),
				    '_mollie_customer_id'  => array (
					    'value' => $subscription->get_meta( '_mollie_customer_id', true ),
					    'label' => 'Mollie Customer ID',
				    ),
			    ),
		    );
	    }
        return $payment_meta;
    }

    /**
     * @param $payment_method_id
     * @param $payment_meta
     * @throws Exception
     */
    public function validate_subscription_payment_meta( $payment_method_id, $payment_meta )
    {
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
    public function update_failing_payment_method( $subscription, $renewal_order )
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    update_post_meta( $subscription->id, '_mollie_customer_id', $renewal_order->mollie_customer_id );
		    update_post_meta( $subscription->id, '_mollie_payment_id', $renewal_order->mollie_payment_id );
	    } else {
		    $subscription = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $subscription->id );
		    $subscription->update_meta_data( '_mollie_customer_id', $renewal_order->mollie_customer_id );
		    $subscription->update_meta_data( '_mollie_payment_id', $renewal_order->mollie_payment_id );
		    $subscription->save();
	    }
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
    protected function is_subscription( $order_id )
    {
        return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
    }

}
