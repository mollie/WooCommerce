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

            // A resubscribe order to record a customer resubscribing to an expired or cancelled subscription.
            add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );

	        // After creating a renewal order to record a scheduled subscription payment with the same post meta, order items etc.
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
	        'subscription_payment_method_change',
	        'subscription_payment_method_change_customer',
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
	protected function getRecurringPaymentRequestData( $order, $customer_id ) {

		// TODO David: is this still used?
		$settings_helper     = Mollie_WC_Plugin::getSettingsHelper();
		$payment_description = __( 'Order', 'woocommerce' ) . ' ' . $order->get_order_number();
		$payment_locale      = $settings_helper->getPaymentLocale();
		$mollie_method       = $this->getMollieMethodId();
		$selected_issuer     = $this->getSelectedIssuer();
		$return_url          = $this->getReturnUrl( $order );
		$webhook_url         = $this->getWebhookUrl( $order );

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			$data = array_filter( array (
				'amount'       => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order->get_total(), Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description'  => $payment_description,
				'redirectUrl'  => $return_url,
				'webhookUrl'   => $webhook_url,
				'method'       => $mollie_method,
				'issuer'       => $selected_issuer,
				'locale'       => $payment_locale,
				'metadata'     => array (
					'order_id' => $order->id,
				),
				'sequenceType' => 'recurring',
				'customerId'   => $customer_id,
			) );
		} else {

			$data = array_filter( array (
				'amount'       => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order->get_total(), Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description'  => $payment_description,
				'redirectUrl'  => $return_url,
				'webhookUrl'   => $webhook_url,
				'method'       => $mollie_method,
				'issuer'       => $selected_issuer,
				'locale'       => $payment_locale,
				'metadata'     => array (
					'order_id' => $order->get_id(),
				),
				'sequenceType' => 'recurring',
				'customerId'   => $customer_id,
			) );
		}

		return $data;
	}

	/**
	 * @param $renewal_order
	 *
	 * @return void
	 */
	public function update_subscription_status_for_direct_debit( $renewal_order ) {

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
				'mollie_wc_gateway_bancontact',
				'mollie_wc_gateway_belfius',
				'mollie_wc_gateway_directdebit',
				'mollie_wc_gateway_eps',
				'mollie_wc_gateway_giropay',
				'mollie_wc_gateway_ideal',
				'mollie_wc_gateway_inghomepay',
				'mollie_wc_gateway_kbc',
				'mollie_wc_gateway_mistercash',
				'mollie_wc_gateway_sofort',
			);

			if ( in_array( $current_method, $methods_needing_update ) == false ) {
				return;
			}

			// Check if WooCommerce Subscriptions Failed Recurring Payment Retry System is in-use, if it is, don't update subscription status
			if ( class_exists('WCS_Retry_Manager') && WCS_Retry_Manager::is_retry_enabled() && $subscription->get_date( 'payment_retry' ) > 0 ) {
				Mollie_WC_Plugin::debug( __METHOD__ . ' - WooCommerce Subscriptions Failed Recurring Payment Retry System in use, not updating subscription status to Active!' );

				return;
			}

			// Check that a new payment is made for renewal order
			if ( $mollie_payment_id == null ) {
				return;
			}

			// Update subscription to Active
			try {

				$subscription->update_status( 'active' );
			}
			catch ( Exception $e ) {
				// Already logged by WooCommerce Subscriptions
				Mollie_WC_Plugin::debug( 'Could not update subscription ' . $subscription_id . ' status:' . $e->getMessage() );
			}

			// Add order note to subscription explaining the change
			$subscription->add_order_note(
			/* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
				__( 'Updated subscription from \'On hold\' to \'Active\' until payment fails, because a SEPA Direct Debit payment takes some time to process.', 'mollie-payments-for-woocommerce' )
			);

		}
		return;

	}

	/**
	 * @param          $renewal_total
	 * @param WC_Order $renewal_order
	 *
	 * @return array
	 * @throws Mollie_WC_Exception_InvalidApiKey
	 */
    public function scheduled_subscription_payment( $renewal_total, WC_Order $renewal_order )
    {

	    if ( ! $renewal_order ) {

		    Mollie_WC_Plugin::debug( $this->id . ': Could not load renewal order or process renewal payment.');

		    return array ( 'result' => 'failure' );
	    }

	    $renewal_order_id 	= ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $renewal_order->id : $renewal_order->get_id();

	    // Allow developers to hook into the subscription renewal payment before it processed
	    do_action(Mollie_WC_Plugin::PLUGIN_ID . '_before_renewal_payment_created', $renewal_order);

	    Mollie_WC_Plugin::debug($this->id . ': Try to create renewal payment for renewal order ' . $renewal_order_id);
        $initial_order_status = $this->getInitialOrderStatus();

        // Overwrite plugin-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status);

        // Overwrite gateway-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status);

	    // Check if test mode is enabled
	    $test_mode = $this->isTestModeEnabledForRenewalOrder($renewal_order);

        // Get Mollie customer ID
        $customer_id    = $this->getOrderMollieCustomerId($renewal_order);

	    $subscriptions                  = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
	    $subscription                   = array_pop( $subscriptions ); // Just need one valid subscription
	    $subscription_id                = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $subscription->id : $subscription->get_id();
	    $subscription_mollie_payment_id = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? get_post_meta( $subscription_id, '_mollie_payment_id', $single = true ) : $subscription->get_meta( '_mollie_payment_id' );

	    if ( ! empty( $subscription_mollie_payment_id ) && ! empty( $subscription )  ) {
		    $customer_id = $this->restore_mollie_customer_id_and_mandate( $customer_id, $subscription_mollie_payment_id, $subscription );
	   }

        // Get all data for the renewal payment
        $data = $this->getRecurringPaymentRequestData($renewal_order, $customer_id);

        // Allow filtering the renewal payment data
        $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $renewal_order);

        // Create a renewal payment
        try
        {

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_create_payment', $data, $renewal_order);
            $payment = null;

	        // Get all mandates for the customer ID
            try
            {
                Mollie_WC_Plugin::debug($this->id . ': Try to get all mandates for renewal order ' . $renewal_order_id . ' with customer ID ' . $customer_id );
                $mandates =  Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->customers->get($customer_id)->mandates();
                $validMandate = false;
                foreach ($mandates as $mandate) {
                    if ($mandate->status == 'valid') {
                        $validMandate = true;
                        $data['method'] = $mandate->method;
                        break;
                    }
                }

            }
            catch ( Mollie\Api\Exceptions\ApiException $e ) {
	            throw new \Mollie\Api\Exceptions\ApiException( sprintf( __( 'The customer (%s) could not be used or found. ' . $e->getMessage(), 'mollie-payments-for-woocommerce-mandate-problem' ), $customer_id ) );
            }

	        // Check that there is at least one valid mandate
	        try {
		        if ( $validMandate ) {
			        Mollie_WC_Plugin::debug( $this->id . ': Valid mandate found for renewal order ' . $renewal_order_id );
			        $payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->create( $data );
		        } else {
			        throw new \Mollie\Api\Exceptions\ApiException( sprintf( __( 'The customer (%s) does not have a valid mandate.', 'mollie-payments-for-woocommerce-mandate-problem' ), $customer_id ) );
		        }
	        }
	        catch ( Mollie\Api\Exceptions\ApiException $e ) {
		        throw $e;
	        }

	        // Update first payment method to actual recurring payment method used for renewal order
	        $this->updateFirstPaymentMethodToRecurringPaymentMethod( $renewal_order, $renewal_order_id, $payment );

			// Log successful creation of payment
	        Mollie_WC_Plugin::debug( $this->id . ': Renewal payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $renewal_order_id . ' payment json response: ' . json_encode( $payment ) );

	        // Unset & set active Mollie payment
	        // Get correct Mollie Payment Object
	        $payment_object = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject( $payment );
	        $payment_object->unsetActiveMolliePayment($renewal_order_id);
            $payment_object->setActiveMolliePayment($renewal_order_id, $payment);

            // Set Mollie customer
	        Mollie_WC_Plugin::getDataHelper()->setUserMollieCustomerIdAtSubscription($renewal_order_id, $payment_object::$customerId);

            // Tell WooCommerce a new payment was created for the order/subscription
            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_payment_created', $payment, $renewal_order);

            // Update order status and add order note
            $this->_updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment);

            // Update status of subscriptions with payment method SEPA Direct Debit or similar
	        $this->update_subscription_status_for_direct_debit( $renewal_order );

	        // Tell WooCommerce a new payment was created for the order/subscription
	        do_action(Mollie_WC_Plugin::PLUGIN_ID . '_after_renewal_payment_created', $payment, $renewal_order);

            return array(
                'result'   => 'success',
            );
        }
        catch ( Mollie\Api\Exceptions\ApiException $e ) {

	        Mollie_WC_Plugin::debug( $this->id . ': Failed to create payment for order ' . $renewal_order_id . ': ' . $e->getMessage() );

	        /* translators: Placeholder 1: Payment method title */
	        $message = sprintf( __( 'Could not create %s renewal payment.', 'mollie-payments-for-woocommerce' ), $this->title );
	        $message .= ' ' . $e->getMessage();
	        $renewal_order->update_status( 'failed', $message );

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

			    // If subscription does not contain the mode, try getting it from the parent order
			    if ( empty( $paymentMode ) ) {
				    $parent_order = $subscription->order->id;
				    $paymentMode = get_post_meta( $parent_order, '_mollie_payment_mode', true );
			    }

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

			    // If subscription does not contain the mode, try getting it from the parent order
			    if ( empty( $paymentMode ) ) {
			    	$parent_order = new WC_Order($subscription->get_parent_id());
				    $paymentMode = $parent_order->get_meta( '_mollie_payment_mode', true );
			    }

			    if ($paymentMode == self::PAYMENT_TEST_MODE){
				    $result = true;
				    break;
			    }
		    }
	    }

        return $result;
    }

	/**
	 * @param WC_Order                            $renewal_order
	 * @param                                     $renewal_order_id
	 * @param Mollie\Api\Resources\Payment        $payment
	 *
	 */
	public function updateFirstPaymentMethodToRecurringPaymentMethod( $renewal_order, $renewal_order_id, $payment ) {

		// Update first payment method to actual recurring payment method used for renewal order, this is
		// for subscriptions where the first order used methods like iDEAL as first payment and
		// later renewal orders switch to SEPA Direct Debit.

		$methods_needing_update = array (
			'mollie_wc_gateway_bancontact',
			'mollie_wc_gateway_belfius',
			'mollie_wc_gateway_eps',
			'mollie_wc_gateway_giropay',
			'mollie_wc_gateway_ideal',
			'mollie_wc_gateway_inghomepay',
			'mollie_wc_gateway_kbc',
			'mollie_wc_gateway_mistercash',
			'mollie_wc_gateway_sofort',
		);

		$current_method = get_post_meta( $renewal_order_id, '_payment_method', $single = true );
		if ( in_array( $current_method, $methods_needing_update ) && $payment->method == 'directdebit' ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				update_post_meta( $renewal_order_id, '_payment_method', 'mollie_wc_gateway_directdebit' );
				update_post_meta( $renewal_order_id, '_payment_method_title', 'SEPA Direct Debit' );
			} else {
				try {
					$renewal_order->set_payment_method( 'mollie_wc_gateway_directdebit' );
					$renewal_order->set_payment_method_title( 'SEPA Direct Debit' );
					$renewal_order->save();
				}
				catch ( WC_Data_Exception $e ) {
					Mollie_WC_Plugin::debug( 'Updating payment method to SEPA Direct Debit failed for renewal order: ' . $renewal_order_id );
				}
			}
		}

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

	    $payment_method_title = $this->getPaymentMethodTitle($payment);

        $renewal_order->add_order_note(sprintf(
        /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%s payment started (%s).', 'mollie-payments-for-woocommerce'),
            $payment_method_title,
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
		    delete_post_meta( $renewal_order->id, '_mollie_payment_id' );
		    delete_post_meta( $renewal_order->id, '_mollie_cancelled_payment_id' );
	    } else {
		    $renewal_order->delete_meta_data( '_mollie_payment_id' );
		    $renewal_order->delete_meta_data( '_mollie_cancelled_payment_id' );
		    $renewal_order->save();
	    }

        return $renewal_order;
    }

	/**
	 * @param $payment_meta
	 * @param $subscription
	 *
	 * @return mixed
	 * @throws \Mollie\Api\Exceptions\ApiException
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_payment_id   = get_post_meta( $subscription->id, '_mollie_payment_id', true );
			$mollie_payment_mode = get_post_meta( $subscription->id, '_mollie_payment_mode', true );
			$mollie_customer_id  = get_post_meta( $subscription->id, '_mollie_customer_id', true );
		} else {
			$mollie_payment_id   = $subscription->get_meta( '_mollie_payment_id', true );
			$mollie_payment_mode = $subscription->get_meta( '_mollie_payment_mode', true );
			$mollie_customer_id  = $subscription->get_meta( '_mollie_customer_id', true );
		}

		$payment_meta[ $this->id ] = array (
			'post_meta' => array (
				'_mollie_payment_id'   => array (
					'value' => $mollie_payment_id,
					'label' => 'Mollie Payment ID',
				),
				'_mollie_payment_mode' => array (
					'value' => $mollie_payment_mode,
					'label' => 'Mollie Payment Mode',
				),
				'_mollie_customer_id'  => array (
					'value' => $mollie_customer_id,
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
    public function validate_subscription_payment_meta( $payment_method_id, $payment_meta )
    {
        if ( $this->id === $payment_method_id ) {

        	// Check that a Mollie Customer ID is entered
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
	 *
	 * @return array
	 * @throws \Mollie\Api\Exceptions\ApiException
	 * @throws \Mollie_WC_Exception_InvalidApiKey
	 */
    public function process_payment ($order_id)
    {
        $isSubscription = Mollie_WC_Plugin::getDataHelper()->isSubscription($order_id);
        if ($isSubscription){
            $result = $this->process_subscription_payment($order_id);
            return $result;
        }

        $result = parent::process_payment($order_id);
        return $result;

    }

	/**
	 * @param $mollie_customer_id
	 * @param $mollie_payment_id
	 * @param $subscription
	 *
	 * @return string
	 */
	public function restore_mollie_customer_id_and_mandate( $mollie_customer_id, $mollie_payment_id, $subscription ) {

		try {
			// Get subscription ID
			$subscription_id = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $subscription->id : $subscription->get_id();

			// Get full payment object from Mollie API
			$payment_object_resource = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject( $mollie_payment_id );

			//
			// If there is no known customer ID, try to get it from the API
			//

			if ( empty ( $mollie_customer_id ) ) {
				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: no valid customer ID found, trying to restore from Mollie API payment (' . $mollie_payment_id . ').' );

				// Try to get the customer ID from the payment object
				$mollie_customer_id = $payment_object_resource->getMollieCustomerIdFromPaymentObject( $mollie_payment_id );

				if ( empty( $mollie_customer_id ) ) {
					Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: stopped processing, no customer ID found for this customer/payment combination.' );

					return $mollie_customer_id;
				}

				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: customer ID (' . $mollie_customer_id . ') found, verifying status of customer and mandate(s).' );

			}

			//
			// Check for valid mandates
			//

			$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
			$test_mode       = $settings_helper->isTestModeEnabled();

			// Get the WooCommerce payment gateway for this subscription
			$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $subscription );

			if ( ! $gateway || ! ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: stopped processing, not a Mollie payment gateway, could not restore customer ID.' );

				return $mollie_customer_id;
			}

			$mollie_method = $gateway->getMollieMethodId();

			// Check that first payment method is related to SEPA Direct Debit and update
			$methods_needing_update = array (
				'bancontact',
				'belfius',
				'eps',
				'giropay',
				'ideal',
				'inghomepay',
				'kbc',
				'mistercash',
				'sofort',
			);

			if ( in_array( $mollie_method, $methods_needing_update ) != false ) {
				$mollie_method = 'directdebit';
			}

			// Get all mandates for the customer
			$mandates = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $mollie_customer_id );

			// Check credit card payments and mandates
			if ( $mollie_method == 'creditcard' && ! $mandates->hasValidMandateForMethod( $mollie_method ) ) {
				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: failed! No valid mandate for payment method ' . $mollie_method . ' found.' );

				return $mollie_customer_id;
			}

			// Get a Payment object from Mollie to check for paid status
			$payment_object = $payment_object_resource->getPaymentObject( $mollie_payment_id );

			// Extra check that first payment was not sequenceType first
			$sequence_type = $payment_object_resource->getSequenceTypeFromPaymentObject( $mollie_payment_id );

			// Check SEPA Direct Debit payments and mandates
			if ( $mollie_method == 'directdebit' && ! $mandates->hasValidMandateForMethod( $mollie_method ) && $payment_object->isPaid() && $sequence_type == 'oneoff' ) {

				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: no valid mandate for payment method ' . $mollie_method . ' found, trying to create one.' );

				$options = $payment_object_resource->getMollieCustomerIbanDetailsFromPaymentObject( $mollie_payment_id );

				// consumerName can be empty for Bancontact payments, in that case use the WooCommerce customer name
				if ( empty( $options['consumerName'] ) ) {

					$billing_first_name = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $subscription->billing_first_name : $subscription->get_billing_first_name();
					$billing_last_name  = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $subscription->billing_last_name : $subscription->get_billing_last_name();

					$options['consumerName'] = $billing_first_name . ' ' . $billing_last_name;
				}

				// Set method
				$options['method'] = $mollie_method;

				$customer = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $mollie_customer_id );
				Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->mandates->createFor( $customer, $options );

				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: mandate created successfully, customer restored.' );

			} else {
				Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: the subscription doesn\'t meet the conditions for a mandate restore.' );
			}

			return $mollie_customer_id;

		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {

			Mollie_WC_Plugin::debug( __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: customer id and mandate restore failed. ' . $e->getMessage() );

			return $mollie_customer_id;
		}
	}

}
