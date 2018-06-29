<?php

use Mollie\Api\Types\PaymentMethod;

abstract class Mollie_WC_Gateway_Abstract extends WC_Payment_Gateway
{
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD    = 'on-hold';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'canceled';
    // Mollie uses canceled (US English spelling), WooCommerce and this plugin use cancelled.
    const STATUS_FAILED     = 'failed';

    /**
     * @var string
     */
    protected $default_title;

    /**
     * @var string
     */
    protected $default_description;

    /**
     * @var bool
     */
    protected $display_logo;

	/**
	 * Recurring total, zero does not define a recurring total
	 *
	 * @var int
	 */
	public $recurring_totals = 0;

    /**
     *
     */
    public function __construct ()
    {
        // No plugin id, gateway id is unique enough
        $this->plugin_id    = '';
        // Use gateway class name as gateway id
        $this->id           = strtolower(get_class($this));
        // Set gateway title (visible in admin)
        $this->method_title = 'Mollie - ' . $this->getDefaultTitle();
        $this->method_description = $this->getSettingsDescription();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->display_logo = $this->get_option('display_logo') == 'yes';

        $this->_initDescription();
        $this->_initIcon();

        if(!has_action('woocommerce_thankyou_' . $this->id)) {
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        }

        add_action('woocommerce_api_' . $this->id, array($this, 'onWebhookAction'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_email_after_order_table', array($this, 'displayInstructions'), 10, 3);

	    // Adjust title and text on Order Received page in some cases, see issue #166
	    add_filter( 'the_title', array ( $this, 'onOrderReceivedTitle' ), 10, 2 );
	    add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'onOrderReceivedText'), 10, 2 );

	    /* Override show issuers dropdown? */
	    if ( $this->get_option( 'issuers_dropdown_shown', 'yes' ) == 'no' ) {
		    $this->has_fields = false;
	    }

        if (!$this->isValidForUse())
        {
            // Disable gateway if it's not valid for use
            $this->enabled = false;
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'mollie-payments-for-woocommerce'),
                'type'        => 'checkbox',
                'label'       => sprintf(__('Enable %s', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default'     => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'mollie-payments-for-woocommerce'),
                'type'        => 'text',
                'description' => sprintf(__('This controls the title which the user sees during checkout. Default <code>%s</code>', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default'     => $this->getDefaultTitle(),
                'desc_tip'    => true,
            ),
            'display_logo' => array(
                'title'       => __('Display logo', 'mollie-payments-for-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Display logo on checkout page. Default <code>enabled</code>', 'mollie-payments-for-woocommerce'),
                'default'     => 'yes'
            ),
            'description' => array(
                'title'       => __('Description', 'mollie-payments-for-woocommerce'),
                'type'        => 'textarea',
                'description' => sprintf(__('Payment method description that the customer will see on your checkout. Default <code>%s</code>', 'mollie-payments-for-woocommerce'), $this->getDefaultDescription()),
                'default'     => $this->getDefaultDescription(),
                'desc_tip'    => true,
            ),
        );

        if ($this->paymentConfirmationAfterCoupleOfDays())
        {
            $this->form_fields['initial_order_status'] = array(
                'title'       => __('Initial order status', 'mollie-payments-for-woocommerce'),
                'type'        => 'select',
                'options'     => array(
                    self::STATUS_ON_HOLD => wc_get_order_status_name(self::STATUS_ON_HOLD) . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')',
                    self::STATUS_PENDING => wc_get_order_status_name(self::STATUS_PENDING),
                ),
                'default'     => self::STATUS_ON_HOLD,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => sprintf(
                    __('Some payment methods take longer than a few hours to complete. The initial order state is then set to \'%s\'. This ensures the order is not cancelled when the setting %s is used.', 'mollie-payments-for-woocommerce'),
                    wc_get_order_status_name(self::STATUS_ON_HOLD),
                    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory') . '" target="_blank">' . __('Hold Stock (minutes)', 'woocommerce') . '</a>'
                ),
            );
        }
    }

    /**
     * @return string
     */
    public function getIconUrl ()
    {

    	// In checkout, show the creditcards.svg with multiple logo's
    	if ( $this->getMollieMethodId() == PaymentMethod::CREDITCARD  && !is_admin()) {
		    return Mollie_WC_Plugin::getPluginUrl('assets/images/' . $this->getMollieMethodId() . 's.svg');
	    }

        return Mollie_WC_Plugin::getPluginUrl('assets/images/' . $this->getMollieMethodId() . '.svg');
    }

	/**
	 * @return string
	 */
	public function getIssuerIconUrl( $issuer_id ) {
		return Mollie_WC_Plugin::getPluginUrl( 'assets/images/' . $issuer_id . '.svg' );
	}

    protected function _initIcon ()
    {
        if ($this->display_logo)
        {
            $default_icon = $this->getIconUrl();
            $this->icon   = apply_filters($this->id . '_icon_url', $default_icon);
        }
    }

    protected function _initDescription ()
    {
        $description = $this->get_option('description', '');

        $this->description = $description;
    }

    public function admin_options ()
    {
        if (!$this->enabled && count($this->errors))
        {
            echo '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'mollie-payments-for-woocommerce') . '</strong>: '
                . implode('<br/>', $this->errors)
                . '</p></div>';

            return;
        }

        parent::admin_options();
    }

    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    protected function isValidForUse()
    {
	    $settings = Mollie_WC_Plugin::getSettingsHelper();

        if (!$this->isValidApiKeyProvided())
        {
            $test_mode = $settings->isTestModeEnabled();

            $this->errors[] = ($test_mode ? __('Test mode enabled.', 'mollie-payments-for-woocommerce') . ' ' : '') . sprintf(
                /* translators: The surrounding %s's Will be replaced by a link to the global setting page */
                    __('No API key provided. Please %sset you Mollie API key%s first.', 'mollie-payments-for-woocommerce'),
                    '<a href="' . $settings->getGlobalSettingsUrl() . '">',
                    '</a>'
                );

            return false;
        }

        if (null === $this->getMollieMethod())
        {
            $this->errors[] = sprintf(
            /* translators: Placeholder 1: payment method title. The surrounding %s's Will be replaced by a link to the Mollie profile */
                __('%s not enabled in your Mollie profile. You can enabled it by editing your %sMollie profile%s.', 'mollie-payments-for-woocommerce'),
                $this->getDefaultTitle(),
                '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                '</a>'
            );

            return false;
        }

        if (!$this->isCurrencySupported())
        {
            $this->errors[] = sprintf(
            /* translators: Placeholder 1: WooCommerce currency, placeholder 2: Supported Mollie currencies */
                __('Current shop currency %s not supported by Mollie. Read more about %ssupported currencies and payment methods.%s ', 'mollie-payments-for-woocommerce'),
                get_woocommerce_currency(),
                '<a href="https://help.mollie.com/hc/en-us/articles/360003980013-Which-currencies-are-supported-and-what-is-the-settlement-currency-" target="_blank">',
                '</a>'
            );

            return false;
        }

        return true;
    }

	/**
	 * Check if the gateway is available for use
	 *
	 * @return bool
	 */
	public function is_available() {

		// In WooCommerce check if the gateway is available for use (WooCommerce settings)
		if ( $this->enabled != 'yes' ) {

			return false;
		}

		// Only in WooCommerce checkout, check min/max amounts
		if ( WC()->cart ) {

			// Check the current (normal) order total
			$order_total = $this->get_order_total();

			// Get the correct currency for this payment or order
			// On order-pay page, oorder is already created and has an order currency
			// On checkout, order is not created, use get_woocommerce_currency
			global $wp;
			if ( ! empty( $wp->query_vars['order-pay'] ) ) {
				$order_id = $wp->query_vars['order-pay'];
				$order    = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

				$currency = $order->get_currency();
			} else {
				$currency = get_woocommerce_currency();
			}

			$filters = array (
				'amount'       => array (
					'currency' => $currency,
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order_total, $currency )
				),
				'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_ONEOFF
			);

			// For regular payments, check available payment methods, but ignore SSD gateway (not shown in checkout)
			$status = ( $this->id !== 'mollie_wc_gateway_directdebit' ) ? $this->isAvailableMethodInCheckout( $filters ) : false;

			// Do extra checks if WooCommerce Subscriptions is installed
			if ( class_exists( 'WC_Subscriptions' ) ) {

				// Check recurring totals against recurring payment methods for future renewal payments
				$recurring_totals = $this->get_recurring_total();

				if ( ! empty( $recurring_totals ) ) {
					foreach ( $recurring_totals as $recurring_total ) {

						// First check recurring payment methods CC and SDD
						$filters = array (
							'amount'       => array (
								'currency' => $currency,
								'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $recurring_total, $currency )
							),
							'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_RECURRING
						);

						$status = $this->isAvailableMethodInCheckout( $filters );

					}

					// Check available first payment methods with today's order total, but ignore SSD gateway (not shown in checkout)
					if ( $this->id !== 'mollie_wc_gateway_directdebit' ) {
						$filters = array (
							'amount'       => array (
								'currency' => $currency,
								'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order_total, $currency )
							),
							'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_FIRST
						);

						$status = $this->isAvailableMethodInCheckout( $filters );
					}
				}
			}

			return $status;

		}

		return true;
	}

    /**
     * Will the payment confirmation be delivered after a couple of days.
     *
     * Overwrite this method for payment gateways where the payment confirmation takes a couple of days.
     * When this method return true, a new setting will be available where the merchant can set the initial
     * payment state: on-hold or pending
     *
     * @return bool
     */
    protected function paymentConfirmationAfterCoupleOfDays ()
    {
        return false;
    }

	/**
	 * @param int $order_id
	 *
	 * @throws \Mollie\Api\Exceptions\ApiException
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

		if ( ! $order ) {
			Mollie_WC_Plugin::debug( $this->id . ': Could not process payment, order ' . $order_id . ' not found.' );

			Mollie_WC_Plugin::addNotice( sprintf( __( 'Could not load order %s', 'mollie-payments-for-woocommerce' ), $order_id ), 'error' );

			return array ( 'result' => 'failure' );
		}

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			Mollie_WC_Plugin::debug( $this->id . ': Start process_payment for order ' . $order->id, true );
		} else {
			Mollie_WC_Plugin::debug( $this->id . ': Start process_payment for order ' . $order->get_id(), true );
		}

		$initial_order_status = $this->getInitialOrderStatus();

		// Overwrite plugin-wide
		$initial_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status );

		// Overwrite gateway-wide
		$initial_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status );

		$settings_helper = Mollie_WC_Plugin::getSettingsHelper();

		// Is test mode enabled?
		$test_mode          = $settings_helper->isTestModeEnabled();
		$customer_id        = $this->getUserMollieCustomerId( $order, $test_mode );
		$paymentRequestData = $this->getPaymentRequestData( $order, $customer_id );

		$data = array_filter( $paymentRequestData );

		$data = apply_filters( 'woocommerce_' . $this->id . '_args', $data, $order );

		//
		// PROCESS SUBSCRIPTION SWITCH - If this is a subscription switch and customer has a valid mandate, process the order internally
		//
		if ( ( '0.00' === $order->get_total() ) && ( $this->is_subscription( $order_id ) == true ) &&
		     0 != $order->get_user_id() && ( wcs_order_contains_switch( $order ) )
		) {

			try {
				Mollie_WC_Plugin::debug( $this->id . ': Subscription switch started, fetching mandate(s) for order #' . $order_id );
				$mandates = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $customer_id )->mandates();
				$validMandate = false;
				foreach ( $mandates as $mandate ) {
					if ( $mandate->status == 'valid' ) {
						$validMandate   = true;
						$data['method'] = $mandate->method;
						break;
					}
				}
				if ( $validMandate ) {

					$order->payment_complete();

					$order->add_order_note( sprintf(
						__( 'Order completed internally because of an existing valid mandate at Mollie.', 'mollie-payments-for-woocommerce' ) ) );

					Mollie_WC_Plugin::debug( $this->id . ': Subscription switch completed, valid mandate for order #' . $order_id );

					return array (
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);

				} else {
					Mollie_WC_Plugin::debug( $this->id . ': Subscription switch failed, no valid mandate for order #' . $order_id );
					Mollie_WC_Plugin::addNotice( __( 'Subscription switch failed, no valid mandate found. Place a completely new order to change your subscription.', 'mollie-payments-for-woocommerce' ), 'error' );
					throw new Mollie\Api\Exceptions\ApiException( __( 'Subscription switch failed, no valid mandate.', 'mollie-payments-for-woocommerce' ) );
				}
			}
			catch ( Mollie\Api\Exceptions\ApiException $e ) {
				if ( $e->getField() ) {
					throw $e;
				}
			}

			return array ( 'result' => 'failure' );

		}

		//
		// PROCESS REGULAR PAYMENT
		//
		try {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				Mollie_WC_Plugin::debug( $this->id . ': Create payment for order ' . $order->id, true );
			} else {
				Mollie_WC_Plugin::debug( $this->id . ': Create payment for order ' . $order->get_id(), true );
			}

			do_action( Mollie_WC_Plugin::PLUGIN_ID . '_create_payment', $data, $order );

			// Create Mollie payment with customer id.
			try {
				$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->create( $data );
			}
			catch ( Mollie\Api\Exceptions\ApiException $e ) {
				if ( $e->getField() !== 'customerId' ) {
					throw $e;
				}

				// Retry without customer id.
				unset( $data['customerId'] );
				$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->create( $data );
			}

			$this->saveMollieInfo( $order, $payment );

			do_action( Mollie_WC_Plugin::PLUGIN_ID . '_payment_created', $payment, $order );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				Mollie_WC_Plugin::debug( $this->id . ': Payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $order->id );
			} else {
				Mollie_WC_Plugin::debug( $this->id . ': Payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $order->get_id() );
			}

			// Update initial order status for payment methods where the payment status will be delivered after a couple of days.
			// See: https://www.mollie.com/nl/docs/status#expiry-times-per-payment-method
			// Status is only updated if the new status is not the same as the default order status (pending)
			if ( ( $payment->method == 'banktransfer' ) || ( $payment->method == 'directdebit' ) ) {

				// Don't change the status of the order if it's Partially Paid
				// This adds support for WooCommerce Deposits (by Webtomizer)
				// See https://github.com/mollie/WooCommerce/issues/138

				$order_status = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $order->status : $order->get_status();

				if ( $order_status != 'wc-partially-paid ' ) {

					$this->updateOrderStatus(
						$order,
						$initial_order_status,
						__( 'Awaiting payment confirmation.', 'mollie-payments-for-woocommerce' ) . "\n"
					);

				}
			}

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
				__( '%s payment started (%s).', 'mollie-payments-for-woocommerce' ),
				$this->method_title,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				Mollie_WC_Plugin::debug( "For order " . $order->id . " redirect user to Mollie Checkout URL: " . $payment->getCheckoutUrl() );
			} else {
				Mollie_WC_Plugin::debug( "For order " . $order->get_id() . " redirect user to Mollie Checkout URL: " . $payment->getCheckoutUrl() );
			}

			return array (
				'result'   => 'success',
				'redirect' => $this->getProcessPaymentRedirect( $order, $payment ),
			);
		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				Mollie_WC_Plugin::debug( $this->id . ': Failed to create payment for order ' . $order->id . ': ' . $e->getMessage() );
			} else {
				Mollie_WC_Plugin::debug( $this->id . ': Failed to create payment for order ' . $order->get_id() . ': ' . $e->getMessage() );
			}

			/* translators: Placeholder 1: Payment method title */
			$message = sprintf( __( 'Could not create %s payment.', 'mollie-payments-for-woocommerce' ), $this->title );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message .= ' ' . $e->getMessage();
			}

			Mollie_WC_Plugin::addNotice( $message, 'error' );
		}

		return array ( 'result' => 'failure' );
	}

    /**
     * @param $order
     * @param $payment
     */
    protected function saveMollieInfo($order, $payment)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    // Set active Mollie payment
		    Mollie_WC_Plugin::getDataHelper()->setActiveMolliePayment($order->id, $payment);

		    // Set Mollie customer
		    Mollie_WC_Plugin::getDataHelper()->setUserMollieCustomerId($order->customer_user, $payment->customerId);
	    } else {
		    // Set active Mollie payment
		    Mollie_WC_Plugin::getDataHelper()->setActiveMolliePayment($order->get_id(), $payment);

		    // Set Mollie customer
		    Mollie_WC_Plugin::getDataHelper()->setUserMollieCustomerId($order->get_customer_id(), $payment->customerId);
	    }
    }

    /**
     * @param $order
     * @param $customer_id
     * @return array
     */
    protected function getPaymentRequestData($order, $customer_id)
    {
        $settings_helper     = Mollie_WC_Plugin::getSettingsHelper();
        $payment_description = $settings_helper->getPaymentDescription();
        $payment_locale      = $settings_helper->getPaymentLocale();
        $store_customer      = $settings_helper->shouldStoreCustomer();
        $mollie_method       = $this->getMollieMethodId();
        $selected_issuer     = $this->getSelectedIssuer();
        $return_url          = $this->getReturnUrl($order);
        $webhook_url         = $this->getWebhookUrl($order);



	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

		    $payment_description = strtr($payment_description, array(
			    '{order_number}' => $order->get_order_number(),
			    '{order_date}'   => date_i18n(wc_date_format(), strtotime($order->order_date)),
		    ));

		    // Create billingAddress object
		    $billingAddress                  = new stdClass();
		    $billingAddress->streetAndNumber = $order->billing_address_1;
		    $billingAddress->postalCode      = $order->billing_postcode;
		    $billingAddress->city            = $order->billing_city;
		    $billingAddress->region          = $order->billing_state;
		    $billingAddress->country         = $order->billing_country;

		    // Create shippingAddress object
		    $shippingAddress                  = new stdClass();
		    $shippingAddress->streetAndNumber = $order->shipping_address_1;
		    $shippingAddress->postalCode      = $order->shipping_postcode;
		    $shippingAddress->city            = $order->shipping_city;
		    $shippingAddress->region          = $order->shipping_state;
		    $shippingAddress->country         = $order->shipping_country;

		    $paymentRequestData = array (
			    'amount'          => array (
				    'currency' => $order->get_currency(),
				    'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue($order->get_total(), $order->get_currency() )
			    ),
			    'description'     => $payment_description,
			    'redirectUrl'     => $return_url,
			    'webhookUrl'      => $webhook_url,
			    'method'          => $mollie_method,
			    'issuer'          => $selected_issuer,
			    'locale'          => $payment_locale,
			    'billingAddress'  => $billingAddress,
			    'shippingAddress' => $shippingAddress,
			    'metadata'        => array (
				    'order_id' => $order->id,
			    ),
		    );
	    } else {

		    $payment_description = strtr($payment_description, array(
			    '{order_number}' => $order->get_order_number(),
			    '{order_date}'   => date_i18n(wc_date_format(), $order->get_date_created()->getTimestamp()),
		    ));

		    // Create billingAddress object
		    $billingAddress                  = new stdClass();
		    $billingAddress->streetAndNumber = $order->get_billing_address_1();
		    $billingAddress->postalCode      = $order->get_billing_postcode();
		    $billingAddress->city            = $order->get_billing_city();
		    $billingAddress->region          = $order->get_billing_state();
		    $billingAddress->country         = $order->get_billing_country();

		    // Create shippingAddress object
		    $shippingAddress                  = new stdClass();
		    $shippingAddress->streetAndNumber = $order->get_shipping_address_1();
		    $shippingAddress->postalCode      = $order->get_shipping_postcode();
		    $shippingAddress->city            = $order->get_shipping_city();
		    $shippingAddress->region          = $order->get_shipping_state();
		    $shippingAddress->country         = $order->get_shipping_country();

		    $paymentRequestData = array (
			    'amount'          => array (
				    'currency' => $order->get_currency(),
				    'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue($order->get_total(), $order->get_currency())
			    ),
			    'description'     => $payment_description,
			    'redirectUrl'     => $return_url,
			    'webhookUrl'      => $webhook_url,
			    'method'          => $mollie_method,
			    'issuer'          => $selected_issuer,
			    'locale'          => $payment_locale,
			    'billingAddress'  => $billingAddress,
			    'shippingAddress' => $shippingAddress,
			    'metadata'        => array (
				    'order_id' => $order->get_id(),
			    ),
		    );
	    }

        if ($store_customer)
            $paymentRequestData['customerId'] = $customer_id;

        return $paymentRequestData;

    }

    /**
     * @param $order
     * @param $test_mode
     * @return null|string
     */
    protected function getUserMollieCustomerId($order, $test_mode)
    {
	    $order_customer_id = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $order->customer_user : $order->get_customer_id();

	    return  Mollie_WC_Plugin::getDataHelper()->getUserMollieCustomerId($order_customer_id, $test_mode);
    }

	/**
	 * Redirect location after successfully completing process_payment
	 *
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 *
	 * @return string
	 */
    protected function getProcessPaymentRedirect(WC_Order $order, Mollie\Api\Resources\Payment $payment)
    {
        /*
         * Redirect to payment URL
         */
        return $payment->getCheckoutUrl();
    }

    /**
     * @param WC_Order $order
     * @param string $new_status
     * @param string $note
     * @param bool $restore_stock
     */
    public function updateOrderStatus (WC_Order $order, $new_status, $note = '', $restore_stock = true )
    {
        $order->update_status($new_status, $note);

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

		    switch ($new_status)
		    {
			    case self::STATUS_ON_HOLD:

				    if ( $restore_stock == true ) {
					    if ( ! get_post_meta( $order->id, '_order_stock_reduced', $single = true ) ) {
						    // Reduce order stock
						    $order->reduce_order_stock();

						    Mollie_WC_Plugin::debug( __METHOD__ . ":  Stock for order {$order->id} reduced." );
					    }
				    }

				    break;

			    case self::STATUS_PENDING:
			    case self::STATUS_FAILED:
			    case self::STATUS_CANCELLED:
				    if (get_post_meta($order->id, '_order_stock_reduced', $single = true))
				    {
					    // Restore order stock
					    Mollie_WC_Plugin::getDataHelper()->restoreOrderStock($order);

					    Mollie_WC_Plugin::debug(__METHOD__ . " Stock for order {$order->id} restored.");
				    }

				    break;
		    }

	    } else {

		    switch ($new_status)
		    {
			    case self::STATUS_ON_HOLD:

				    if ( $restore_stock == true ) {
					    if ( ! $order->get_meta( '_order_stock_reduced', true ) ) {
						    // Reduce order stock
						    wc_reduce_stock_levels( $order->get_id() );

						    Mollie_WC_Plugin::debug( __METHOD__ . ":  Stock for order {$order->get_id()} reduced." );
					    }
				    }

				    break;

			    case self::STATUS_PENDING:
			    case self::STATUS_FAILED:
			    case self::STATUS_CANCELLED:
				    if ( $order->get_meta( '_order_stock_reduced', true ) )
				    {
					    // Restore order stock
					    Mollie_WC_Plugin::getDataHelper()->restoreOrderStock($order);

					    Mollie_WC_Plugin::debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
				    }

				    break;
		    }

	    }
    }


    public function onWebhookAction ()
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

	    $order_id = sanitize_text_field( $_GET['order_id'] );
	    $key      = sanitize_text_field( $_GET['key'] );

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
        if (empty($_POST['id']))
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ': No payment ID provided.', true);
            return;
        }

	    $payment_id = sanitize_text_field( $_POST['id'] );
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

        // Log a message that webhook was called, doesn't mean the payment is actually processed
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    Mollie_WC_Plugin::debug($this->id . ": Mollie payment {$payment->id} (" . $payment->mode . ") webhook call for order {$order->id}.", true);
	    } else {
		    Mollie_WC_Plugin::debug($this->id . ": Mollie payment {$payment->id} (" . $payment->mode . ") webhook call for order {$order->get_id()}.", true);
	    }

	    // Order does not need a payment
	    if ( ! $this->orderNeedsPayment( $order ) ) {

		    // Add a debug message that order was already paid for
		    $this->handlePaidOrderWebhook( $order, $payment );

		    // Check and process a possible refund or chargeback
		    $this->processRefunds( $order, $payment );
		    $this->processChargebacks( $order, $payment );

		    return;
	    }

	    // Create the method name based on the payment status
        $method_name = 'onWebhook' . ucfirst($payment->status);


        if (method_exists($this, $method_name))
        {
            $this->{$method_name}($order, $payment);
        }
        else
        {
            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment status, placeholder 3: payment ID */
                __('%s payment %s (%s), not processed.', 'mollie-payments-for-woocommerce'),
                $this->method_title,
                $payment->status,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));
        }

        // Status 200
    }

    /**
     * @param $order
     * @param $payment
     */
	protected function handlePaidOrderWebhook( $order, $payment ) {
		// Duplicate webhook call
		Mollie_WC_Plugin::setHttpResponseCode( 204 );

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order    = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order );
			$order_id = $order->get_id();
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $this->id . ": Order $order_id does not need a payment by Mollie (payment {$payment->id}).", true );

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 */
	protected function processRefunds( WC_Order $order, Mollie\Api\Resources\Payment $payment ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Debug log ID (order id/payment id)
		$log_id = 'order ' . $order_id . ' / payment ' . $payment->id;

		// Add message to log
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for ' . $log_id );
		
		// Make sure there are refunds to process at all
		if ( ! $payment->_links->refunds ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ": No refunds to process for {$log_id}", true );

			return;
		}

		// Check for new refund
		try {

			// Get all refunds for this payment
			$refunds = $payment->refunds();

			// Collect all refund IDs in one array
			$refund_ids = array ();
			foreach ( $refunds as $refund ) {
				$refund_ids[] = $refund->id;
			}


			Mollie_WC_Plugin::debug( __METHOD__ . ' All refund IDs for ' . $log_id . ': ' . json_encode( $refund_ids ) );

			// Get possibly already processed refunds
			if ( $order->meta_exists( '_mollie_processed_refund_ids' ) ) {
				$processed_refund_ids = $order->get_meta( '_mollie_processed_refund_ids', true );
			} else {
				$processed_refund_ids = array ();
			}

			Mollie_WC_Plugin::debug( __METHOD__ . ' Already processed refunds for ' . $log_id . ': ' . json_encode( $processed_refund_ids ) );

			// Order the refund arrays by value (refund ID)
			asort( $refund_ids );
			asort( $processed_refund_ids );

			// Check if there are new refunds that need processing
			if ( $refund_ids != $processed_refund_ids ) {
				// There are new refunds.
				$refunds_to_process = array_diff( $refund_ids, $processed_refund_ids );
				Mollie_WC_Plugin::debug( __METHOD__ . ' Refunds that need to be processed for ' . $log_id . ': ' . json_encode( $refunds_to_process ) );

			} else {
				// No new refunds, stop processing.
				Mollie_WC_Plugin::debug( __METHOD__ . ' No new refunds, stop processing for ' . $log_id );

				return;
			}

			$data_helper = Mollie_WC_Plugin::getDataHelper();
			$order       = $data_helper->getWcOrder( $order_id );

			foreach ( $refunds_to_process as $refund_to_process ) {

				Mollie_WC_Plugin::debug( __METHOD__ . ' New refund ' . $refund_to_process . ' processed in Mollie Dashboard for ' . $log_id . '. Order note added, but order not updated.' );

				$order->add_order_note( sprintf(
					__( 'New refund %s processed in Mollie Dashboard! Order note added, but order not updated.', 'mollie-payments-for-woocommerce' ),
					$refund_to_process
				) );

				$processed_refund_ids[] = $refund_to_process;

			}

			$order->update_meta_data( '_mollie_processed_refund_ids', $processed_refund_ids );
			Mollie_WC_Plugin::debug( __METHOD__ . ' Updated, all processed refunds for ' . $log_id . ': ' . json_encode( $processed_refund_ids ) );

			$order->save();

			return;

		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load refunds for $payment->id: " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 */
	protected function processChargebacks( WC_Order $order, Mollie\Api\Resources\Payment $payment ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Debug log ID (order id/payment id)
		$log_id = 'order ' . $order_id . ' / payment ' . $payment->id;

		// Add message to log
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for ' . $log_id );

		// Make sure there are chargebacks to process at all
		if ( ! $payment->_links->chargebacks ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ": No chargebacks to process for {$log_id}", true );

			return;
		}

		// Check for new chargeback
		try {

			// Get all chargebacks for this payment
			$chargebacks = $payment->chargebacks();

			// Collect all chargeback IDs in one array
			$chargeback_ids = array ();
			foreach ( $chargebacks as $chargeback ) {
				$chargeback_ids[] = $chargeback->id;
			}

			Mollie_WC_Plugin::debug( __METHOD__ . ' All chargeback IDs for ' . $log_id . ': ' . json_encode( $chargeback_ids ) );

			// Get possibly already processed chargebacks
			if ( $order->meta_exists( '_mollie_processed_chargeback_ids' ) ) {
				$processed_chargeback_ids = $order->get_meta( '_mollie_processed_chargeback_ids', true );
			} else {
				$processed_chargeback_ids = array ();
			}

			Mollie_WC_Plugin::debug( __METHOD__ . ' Already processed chargebacks for ' . $log_id . ': ' . json_encode( $processed_chargeback_ids ) );

			// Order the chargeback arrays by value (chargeback ID)
			asort( $chargeback_ids );
			asort( $processed_chargeback_ids );

			// Check if there are new chargebacks that need processing
			if ( $chargeback_ids != $processed_chargeback_ids ) {
				// There are new chargebacks.
				$chargebacks_to_process = array_diff( $chargeback_ids, $processed_chargeback_ids );
				Mollie_WC_Plugin::debug( __METHOD__ . ' Chargebacks that need to be processed for ' . $log_id . ': ' . json_encode( $chargebacks_to_process ) );

			} else {
				// No new chargebacks, stop processing.
				Mollie_WC_Plugin::debug( __METHOD__ . ' No new chargebacks, stop processing for ' . $log_id );

				return;
			}

			$data_helper = Mollie_WC_Plugin::getDataHelper();
			$order       = $data_helper->getWcOrder( $order_id );

			// Update order notes, add message ahout chargeback
			foreach ( $chargebacks_to_process as $chargeback_to_process ) {

				Mollie_WC_Plugin::debug( __METHOD__ . ' New chargeback ' . $chargeback_to_process . ' for ' . $log_id . '. Order note and order status updated.' );

				$order->add_order_note( sprintf(
					__( 'New chargeback %s processed! Order note and order status updated.', 'mollie-payments-for-woocommerce' ),
					$chargeback_to_process
				) );

				$processed_chargeback_ids[] = $chargeback_to_process;
			}

			// Update order status and add general note

			// New order status
			$new_order_status = self::STATUS_ON_HOLD;

			// Overwrite plugin-wide
			$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold', $new_order_status );

			// Overwrite gateway-wide
			$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold_' . $this->id, $new_order_status );

			$paymentMethodTitle = $this->getPaymentMethodTitle( $payment );

			// Update order status for order with charged_back payment, don't restore stock
			$this->updateOrderStatus(
				$order,
				$new_order_status,
				sprintf(
				/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
					__( '%s payment charged back via Mollie (%s). You will need to manually review the payment and adjust product stocks if you use them.', 'mollie-payments-for-woocommerce' ),
					$paymentMethodTitle,
					$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
				),
				$restore_stock = false
			);

			// Send a "Failed order" email to notify the admin
			$emails = WC()->mailer()->get_emails();
			if ( ! empty( $emails ) && ! empty( $order_id ) && ! empty( $emails['WC_Email_Failed_Order'] ) ) {
				$emails['WC_Email_Failed_Order']->trigger( $order_id );
			}


			$order->update_meta_data( '_mollie_processed_chargeback_ids', $processed_chargeback_ids );
			Mollie_WC_Plugin::debug( __METHOD__ . ' Updated, all processed chargebacks for ' . $log_id . ': ' . json_encode( $processed_chargeback_ids ) );

			$order->save();

			return;

		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load chargebacks for $payment->id: " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

	}

	/**
	 * @param WC_Order                  $order
	 * @param Mollie\Api\Resources\Payment $payment
	 */
	protected function onWebhookPaid( WC_Order $order, Mollie\Api\Resources\Payment $payment ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		if ( $payment->isPaid() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
			$woo_version = get_option( 'woocommerce_version', 'Unknown' );

			if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
				$order->payment_complete( $payment->id );
			} else {
				$order->payment_complete();
			}

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to onWebHookPaid for order ' . $order_id );

			$paymentMethodTitle = $this->getPaymentMethodTitle( $payment );
			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce' ),
				$paymentMethodTitle,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			Mollie_WC_Plugin::getDataHelper()->unsetCancelledMolliePaymentId( $order_id );

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing paid order via Mollie plugin fully completed for order ' . $order_id );

		} else {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' payment at Mollie not paid, so no processing for order ' . $order_id );

		}
	}

    /**
     * @param $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {
        $paymentMethodTitle = '';
        if ($payment->method == $this->getMollieMethodId()){
            $paymentMethodTitle = $this->method_title;
        }
        return $paymentMethodTitle;
    }


    /**
     * @param WC_Order $order
     * @param Mollie\Api\Resources\Payment $payment
     */
    protected function onWebhookCancelled(WC_Order $order, Mollie\Api\Resources\Payment $payment)
    {

	    // Get order ID in the correct way depending on WooCommerce version
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $order_id = $order->id;
	    } else {
		    $order_id = $order->get_id();
	    }

	    // Add messages to log
	    Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

	    Mollie_WC_Plugin::getDataHelper()
		                    ->unsetActiveMolliePayment( $order_id, $payment->id )
		                    ->setCancelledMolliePaymentId( $order_id, $payment->id );

	    // What status does the user want to give orders with cancelled payments?
	    $settings_helper     = Mollie_WC_Plugin::getSettingsHelper();
	    $order_status_cancelled_payments      = $settings_helper->getOrderStatusCancelledPayments();

        // New order status
	    if($order_status_cancelled_payments == 'pending' || $order_status_cancelled_payments == null) {
		    $new_order_status = self::STATUS_PENDING;
	    } elseif ($order_status_cancelled_payments == 'cancelled' ) {
		    $new_order_status = self::STATUS_CANCELLED;
	    }

        // Overwrite plugin-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled', $new_order_status);

        // Overwrite gateway-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled_' . $this->id, $new_order_status);

	    // Update order status, but only if there is no payment started by another gateway
	    if ( ! $this->isOrderPaymentStartedByOtherGateway( $order ) ) {
		    $this->updateOrderStatus( $order, $new_order_status );
	    } else {
		    $order_payment_method_title = get_post_meta( $order_id, '_payment_method_title', $single = true );

		    // Add message to log
		    Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $order_payment_method_title . ', so order status not updated.', true );

		    // Add order note
		    $order->add_order_note( sprintf(
		    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
			    __( 'Mollie webhook called, but payment also started via %s, so the order status is not updated.', 'mollie-payments-for-woocommerce' ),
			    $order_payment_method_title
		    ) );
	    }

        $paymentMethodTitle = $this->getPaymentMethodTitle($payment);

        // User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment cancelled (%s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
    }

    /**
     * @param WC_Order $order
     * @param Mollie\Api\Resources\Payment $payment
     */
    protected function onWebhookExpired(WC_Order $order, Mollie\Api\Resources\Payment $payment)
    {

	    // Get order ID in correct way depending on WooCommerce version
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $order_id = $order->id;
		    $mollie_payment_id = get_post_meta( $order_id, '_mollie_payment_id', $single = true );
	    } else {
		    $order_id = $order->get_id();
		    $mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );
	    }

	    // Add messages to log
	    Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

	    // Get payment method title for use in log messages and order notes
	    $paymentMethodTitle = $this->getPaymentMethodTitle($payment);

	    // Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
	    if ( $mollie_payment_id != $payment->id) {
		    Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $mollie_payment_id );

		    $order->add_order_note(sprintf(
		    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
			    __('%s payment expired (%s) but order not cancelled because of another pending payment (%s).', 'mollie-payments-for-woocommerce'),
			    $paymentMethodTitle,
			    $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : ''),
			    $mollie_payment_id
		    ));

	    	return;
	    }

        // New order status
        $new_order_status = self::STATUS_CANCELLED;

        // Overwrite plugin-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired', $new_order_status);

        // Overwrite gateway-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired_' . $this->id, $new_order_status);

	    // Update order status, but only if there is no payment started by another gateway
	    if ( ! $this->isOrderPaymentStartedByOtherGateway( $order ) ) {
		    $this->updateOrderStatus( $order, $new_order_status );
	    } else {
		    $order_payment_method_title = get_post_meta( $order_id, '_payment_method_title', $single = true );

		    // Add message to log
		    Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $order_payment_method_title . ', so order status not updated.', true );

		    // Add order note
		    $order->add_order_note( sprintf(
		    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
			    __( 'Mollie webhook called, but payment also started via %s, so the order status is not updated.', 'mollie-payments-for-woocommerce' ),
			    $order_payment_method_title
		    ) );
	    }

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment expired (%s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));

	    // Remove (old) cancelled payments from this order
	    Mollie_WC_Plugin::getDataHelper()->unsetCancelledMolliePaymentId( $order_id );

    }

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 */
	protected function onWebhookFailed( WC_Order $order, Mollie\Api\Resources\Payment $payment ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// New order status
		$new_order_status = self::STATUS_FAILED;

		// Overwrite plugin-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold', $new_order_status );

		// Overwrite gateway-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold_' . $this->id, $new_order_status );

		$paymentMethodTitle = $this->getPaymentMethodTitle( $payment );

		// Update order status for order with failed payment, don't restore stock
		$this->updateOrderStatus(
			$order,
			$new_order_status,
			sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( '%s payment failed via Mollie (%s).', 'mollie-payments-for-woocommerce' ),
				$paymentMethodTitle,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			)
		);

		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', regular order payment failed.' );

	}

	/**
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function getReturnRedirectUrlForOrder( WC_Order $order ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		Mollie_WC_Plugin::debug( __METHOD__ . " $order_id: Determine what the redirect URL in WooCommerce should be." );

		$data_helper = Mollie_WC_Plugin::getDataHelper();

		if ( $this->orderNeedsPayment( $order ) ) {

			$hasCancelledMolliePayment = $data_helper->hasCancelledMolliePayment( $order_id);

			if ( $hasCancelledMolliePayment ) {

				$settings_helper                 = Mollie_WC_Plugin::getSettingsHelper();
				$order_status_cancelled_payments = $settings_helper->getOrderStatusCancelledPayments();

				// If user set all cancelled payments to also cancel the order,
				// redirect to /checkout/order-received/ with a message about the
				// order being cancelled. Otherwise redirect to /checkout/order-pay/ so
				// customers can try to pay with another payment method.
				if ( $order_status_cancelled_payments == 'cancelled' ) {

					return $this->get_return_url( $order );

				} else {
					Mollie_WC_Plugin::addNotice( __( 'You have cancelled your payment. Please complete your order with a different payment method.', 'mollie-payments-for-woocommerce' ) );

					// Return to order payment page
					if ( method_exists( $order, 'get_checkout_payment_url' ) ) {
						return $order->get_checkout_payment_url( false );
					}
				}

				// Return to order payment page
				if ( method_exists( $order, 'get_checkout_payment_url' ) ) {
					return $order->get_checkout_payment_url( false );
				}

			}

			$payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment($order_id, false );

			if ( ! $payment->isOpen() && ! $payment->isPending() && ! $payment->isPaid() ) {
				Mollie_WC_Plugin::addNotice( __( 'Your payment was not successful. Please complete your order with a different payment method.', 'mollie-payments-for-woocommerce' ) );
				// Return to order payment page
				if ( method_exists( $order, 'get_checkout_payment_url' ) ) {
					return $order->get_checkout_payment_url( false );
				}
			}

		}

		/*
		 * Return to order received page
		 */

		return $this->get_return_url( $order );
	}

    /**
     * Process a refund if supported
     * @param int    $order_id
     * @param float  $amount
     * @param string $reason
     * @return bool|wp_error True or false based on success, or a WP_Error object
     * @since WooCommerce 2.2
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder($order_id);

        if (!$order)
        {
            Mollie_WC_Plugin::debug('process_refund - could not find order ' . $order_id);

            return false;
        }

        try
        {
            $payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment($order_id);

            if (!$payment)
            {
                Mollie_WC_Plugin::debug('process_refund - could not find active Mollie payment for order ' . $order_id);

                return false;
            }
            elseif (!$payment->isPaid())
            {
                Mollie_WC_Plugin::debug('process_refund - could not refund payment ' . $payment->id . ' (not paid). Order ' . $order_id);

                return false;
            }

            Mollie_WC_Plugin::debug('process_refund - create refund - payment: ' . $payment->id . ', order: ' . $order_id . ', amount: ' .  $order->get_currency() . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_create_refund', $payment, $order);

            // Is test mode enabled?
            $test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

	        // Send refund to Mollie
	        $refund = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->refund( $payment, array (
		        'amount'      => array (
			        'currency' => $order->get_currency(),
			        'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $amount, $order->get_currency() )
		        ),
		        'description' => $reason
	        ) );

            Mollie_WC_Plugin::debug('process_refund - refund created - refund: ' . $refund->id . ', payment: ' . $payment->id . ', order: ' . $order_id . ', amount: ' .  $order->get_currency() . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_refund_created', $refund, $order);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %s%s%s - Payment: %s, Refund: %s', 'mollie-payments-for-woocommerce'),
	            $order->get_currency(),
                $amount,
	            (!empty($reason) ? ' (reason: ' . $reason . ')' : ''),
                $refund->paymentId,
                $refund->id
            ));

            return true;
        }
        catch ( \Mollie\Api\Exceptions\ApiException $e )
        {
            return new WP_Error(1, $e->getMessage());
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page ($order_id)
    {
        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder($order_id);

        // Order not found
        if (!$order)
        {
            return;
        }

        // Empty cart
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }

        // Same as email instructions, just run that
        $this->displayInstructions($order, $admin_instructions = false, $plain_text = false);
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool     $admin_instructions (default: false)
     * @param bool     $plain_text (default: false)
     * @return void
     */
    public function displayInstructions(WC_Order $order, $admin_instructions = false, $plain_text = false)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $order_payment_method = $order->payment_method;
	    } else {
		    $order_payment_method = $order->get_payment_method();
	    }

        // Invalid gateway
        if ($this->id !== $order_payment_method)
        {
            return;
        }

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment($order->id);
	    } else {
		    $payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment($order->get_id());
	    }

        // Mollie payment not found or invalid gateway
        if (!$payment || $payment->method != $this->getMollieMethodId())
        {
            return;
        }

        $instructions = $this->getInstructions($order, $payment, $admin_instructions, $plain_text);

        if (!empty($instructions))
        {
            $instructions = wptexturize($instructions);

            if ($plain_text)
            {
                echo $instructions . PHP_EOL;
            }
            else
            {
                echo '<section class="woocommerce-order-details woocommerce-info mollie-instructions" >';
                echo wpautop($instructions) . PHP_EOL;
                echo '</section>';
            }
        }
    }

    /**
     * @param WC_Order                  $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie\Api\Resources\Payment $payment, $admin_instructions, $plain_text)
    {
        // No definite payment status
        if ($payment->isOpen() || $payment->isPending())
        {
            if ($admin_instructions)
            {
                // Message to admin
                return __('We have not received a definite payment status.', 'mollie-payments-for-woocommerce');
            }
            else
            {
                // Message to customer
                return __('We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.', 'mollie-payments-for-woocommerce');
            }
        }
        elseif ($payment->isPaid())
        {
            return sprintf(
            /* translators: Placeholder 1: payment method */
                __('Payment completed with <strong>%s</strong>', 'mollie-payments-for-woocommerce'),
                $this->get_title()
            );
        }

        return null;
    }

	/**
	 * @param WC_Order $order
	 */
	public function onOrderReceivedTitle( $title, $id = null ) {

		if ( is_order_received_page() && get_the_ID() === $id ) {
			global $wp;

			$order = false;
			$order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
			$order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );
			if ( $order_id > 0 ) {
				$order = wc_get_order( $order_id );

				if ( ! is_a( $order, 'WC_Order' ) ) {
					return $title;
				}

				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$order_key_db = $order->order_key;
				} else {
					$order_key_db = $order->get_order_key();
				}

				if ( $order_key_db != $order_key ) {
					$order = false;
				}
			}

			if ( $order == false){
				return $title;
			}

			$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$order_payment_method = $order->payment_method;
			} else {
				$order_payment_method = $order->get_payment_method();
			}

			// Invalid gateway
			if ( $this->id !== $order_payment_method ) {
				return $title;
			}

			// Title for cancelled orders
			if ( $order->has_status( 'cancelled' ) ) {
				$title = __( 'Order cancelled', 'mollie-payments-for-woocommerce' );

				return $title;
			}

			// Checks and title for pending/open orders
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment( $order->id );
			} else {
				$payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment( $order->get_id() );
			}

			// Mollie payment not found or invalid gateway
			if ( ! $payment || $payment->method != $this->getMollieMethodId() ) {
				return $title;
			}

			if ( $payment->isOpen() ) {

				// Add a message to log and order explaining a payment with status "open", only if it hasn't been added already
				if ( get_post_meta( $order_id, '_mollie_open_status_note', true ) !== '1' ) {

					// Get payment method title
					$paymentMethodTitle = $this->getPaymentMethodTitle( $payment );

					// Add message to log
					Mollie_WC_Plugin::debug( $this->id . ': Customer returned to store, but payment still pending for order #' . $order_id . '. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.' );

					// Add message to order as order note
					$order->add_order_note( sprintf(
					/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
						__( '%s payment still pending (%s) but customer already returned to the store. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.', 'mollie-payments-for-woocommerce' ),
						$paymentMethodTitle,
						$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
					) );

					update_post_meta( $order_id, '_mollie_open_status_note', '1' );
				}

				// Update the title on the Order received page to better communicate that the payment is pending.
				$title .= __( ', payment pending.', 'mollie-payments-for-woocommerce' );

				return $title;
			}

		}

		return $title;

	}

	/**
	 * @param WC_Order $order
	 */
	public function onOrderReceivedText( $text, $order ) {
		if ( !is_a( $order, 'WC_Order' ) ) {
			return $text;
		}

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_payment_method = $order->payment_method;
		} else {
			$order_payment_method = $order->get_payment_method();
		}

		// Invalid gateway
		if ( $this->id !== $order_payment_method ) {
			return $text;
		}

		if ( $order->has_status( 'cancelled' ) ) {
			$text = __( 'Your order has been cancelled.', 'mollie-payments-for-woocommerce' );

			return $text;
		}

		return $text;

	}

	/**
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	protected function orderNeedsPayment( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Check whether the order is processed and paid via another gateway
		if ( $this->isOrderPaidByOtherGateway( $order ) ) {
			Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: no, processed by other (non-Mollie) gateway.', true );

			return false;
		}

		// Check whether the order is processed and paid via Mollie
		if ( ! $this->isOrderPaidAndProcessed( $order ) ) {
			Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, not processed by Mollie gateway.', true );

			return true;
		}

		if ( $order->needs_payment() ) {
			Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, WooCommerce thinks order needs payment.', true );

			return true;
		}

		// Has initial order status 'on-hold'
		if ( $this->getInitialOrderStatus() === self::STATUS_ON_HOLD && Mollie_WC_Plugin::getDataHelper()->hasOrderStatus( $order, self::STATUS_ON_HOLD ) ) {
			Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, has status On-Hold. ', true );

			return true;
		}

		return false;
	}

    /**
     * @return \Mollie\Api\Resources\Method|null
     */
	public function getMollieMethod() {

		$test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

		return Mollie_WC_Plugin::getDataHelper()->getPaymentMethod(
			$test_mode,
			$this->getMollieMethodId()
		);

	}

    /**
     * @return string
     */
    protected function getInitialOrderStatus ()
    {
        if ($this->paymentConfirmationAfterCoupleOfDays())
        {
            return $this->get_option('initial_order_status');
        }

        return self::STATUS_PENDING;
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    protected function getReturnUrl (WC_Order $order)
    {
        $site_url   = get_site_url();

	    $return_url = WC()->api_request_url( 'mollie_return' );
	    $return_url = $this->removeTrailingSlashAfterParamater( $return_url );

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $return_url = add_query_arg(array(
			    'order_id'       => $order->id,
			    'key'            => $order->order_key,
		    ), $return_url);
	    } else {
		    $return_url = add_query_arg(array(
			    'order_id'       => $order->get_id(),
			    'key'            => $order->get_order_key(),
		    ), $return_url);
	    }

        $lang_url   = $this->getSiteUrlWithLanguage();
        $return_url = str_replace($site_url, $lang_url, $return_url);

        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_return_url', $return_url, $order);
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    protected function getWebhookUrl (WC_Order $order)
    {
        $site_url    = get_site_url();

	    $webhook_url = WC()->api_request_url( strtolower( get_class( $this ) ) );
	    $webhook_url = $this->removeTrailingSlashAfterParamater( $webhook_url );

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $webhook_url = add_query_arg(array(
			    'order_id' => $order->id,
			    'key'      => $order->order_key,
		    ), $webhook_url);
	    } else {
		    $webhook_url = add_query_arg(array(
			    'order_id' => $order->get_id(),
			    'key'      => $order->get_order_key(),
		    ), $webhook_url);
	    }

        $lang_url    = $this->getSiteUrlWithLanguage();
        $webhook_url = str_replace($site_url, $lang_url, $webhook_url);

        // Some (multilanguage) plugins will add a extra slash to the url (/nl//) causing the URL to redirect and lose it's data.
	    // Status updates via webhook will therefor not be processed. The below regex will find and remove those double slashes.
	    $webhook_url = preg_replace('/([^:])(\/{2,})/', '$1/', $webhook_url);

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order->id . ' webhookUrl: ' . $webhook_url, true );
	    } else {
		    Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order->get_id() . ' webhookUrl: ' . $webhook_url, true );
	    }

        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_webhook_url', $webhook_url, $order);
    }

	/**
	 * Remove a trailing slash after a query string if there is one in the WooCommerce API request URL.
	 * For example WMPL adds a query string with trailing slash like /?lang=de/ to WC()->api_request_url.
	 * This causes issues when we append to that URL with add_query_arg.
	 *
	 * @return string
	 */
	protected function removeTrailingSlashAfterParamater( $url ) {

		if ( strpos( $url, '?' ) ) {
			$url = untrailingslashit( $url );
		}

		return $url;
	}

	/**
	 * Check if any multi language plugins are enabled and return the correct site url.
	 *
	 * @return string
	 */
	protected function getSiteUrlWithLanguage() {
		/**
		 * function is_plugin_active() is not available. Lets include it to use it.
		 */
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$site_url          = get_site_url();
		$polylang_fallback = false;

		if ( is_plugin_active( 'polylang/polylang.php' ) || is_plugin_active( 'polylang-pro/polylang.php' ) ) {

			$lang = PLL()->model->get_language( pll_current_language() );

			if ( empty ( $lang->search_url ) ) {
				$polylang_fallback = true;
			} else {
				$polylang_url = $lang->search_url;
				$site_url     = str_replace( $site_url, $polylang_url, $site_url );
			}
		}

		if ( $polylang_fallback == true || is_plugin_active( 'mlang/mlang.php' ) || is_plugin_active( 'mlanguage/mlanguage.php' ) ) {

			$slug = get_bloginfo( 'language' );
			$pos  = strpos( $slug, '-' );
			if ( $pos !== false ) {
				$slug = substr( $slug, 0, $pos );
			}
			$slug     = '/' . $slug;
			$site_url = str_replace( $site_url, $site_url . $slug, $site_url );

		}

		return $site_url;
	}

    /**
     * @return string|NULL
     */
    protected function getSelectedIssuer ()
    {
        $issuer_id = Mollie_WC_Plugin::PLUGIN_ID . '_issuer_' . $this->id;

        return !empty($_POST[$issuer_id]) ? $_POST[$issuer_id] : NULL;
    }

    /**
     * @return array
     */
    protected function getSupportedCurrencies ()
    {
        $default = array(
        	'AUD',
	        'BGN',
	        'BRL',
	        'CAD',
	        'CHF',
	        'CZK',
	        'DKK',
	        'EUR',
	        'GBP',
	        'HKD',
	        'HRK',
	        'HUF',
	        'ILS',
	        'ISK',
	        'JPY',
	        'MXN',
	        'MYR',
	        'NOK',
	        'NZD',
	        'PHP',
	        'PLN',
	        'RON',
	        'RUB',
	        'SEK',
	        'SGD',
	        'THB',
	        'TWD',
	        'USD',
	        );

        return apply_filters('woocommerce_' . $this->id . '_supported_currencies', $default);
    }

    /**
     * @return bool
     */
    protected function isCurrencySupported ()
    {
        return in_array(get_woocommerce_currency(), $this->getSupportedCurrencies());
    }

    /**
     * @return bool
     */
    protected function isValidApiKeyProvided ()
    {
        $settings  = Mollie_WC_Plugin::getSettingsHelper();
        $test_mode = $settings->isTestModeEnabled();
        $api_key   = $settings->getApiKey($test_mode);

        return !empty($api_key) && preg_match('/^(live|test)_\w+$/', $api_key);
    }


	/**
	 * @return bool
	 */
	protected function setOrderPaidAndProcessed( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
			update_post_meta( $order_id, '_mollie_paid_and_processed', '1' );
		} else {
			$order->update_meta_data( '_mollie_paid_and_processed', '1' );
			$order->save();
		}
		return true;
	}


	/**
	 * @return bool
	 */
	protected function isOrderPaidAndProcessed( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id           = $order->id;
			$paid_and_processed = get_post_meta( $order_id, '_mollie_paid_and_processed', $single = true );
		} else {
			$paid_and_processed = $order->get_meta( '_mollie_paid_and_processed', true );
		}

		return $paid_and_processed;

	}

	/**
	 * @return bool
	 */
	protected function isOrderPaidByOtherGateway( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id           = $order->id;
			$paid_by_other_gateway = get_post_meta( $order_id, '_mollie_paid_by_other_gateway', $single = true );
		} else {
			$paid_by_other_gateway = $order->get_meta( '_mollie_paid_by_other_gateway', true );
		}

		return $paid_by_other_gateway;

	}

	/**
	 * @return bool
	 */
	protected function isOrderPaymentStartedByOtherGateway( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Get the current payment method id for the order
		$payment_method_id = get_post_meta( $order_id, '_payment_method', $single = true );

		// If the current payment method id for the order is not Mollie, return true
		if ( ( strpos( $payment_method_id, 'mollie' ) === false ) ) {

			return true;
		}

		return false;

	}


    /**
     * @return mixed
     */
    abstract public function getMollieMethodId ();

    /**
     * @return string
     */
    abstract public function getDefaultTitle ();

    /**
     * @return string
     */
    abstract protected function getSettingsDescription ();

    /**
     * @return string
     */
    abstract protected function getDefaultDescription ();

	/**
	 * @param $order_id
	 * @return bool
	 */
	protected function is_subscription( $order_id )
	{
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * @return mixed
	 */
	protected function get_recurring_total() {

		if ( isset( WC()->cart ) ) {

			if ( ! empty( WC()->cart->recurring_carts ) ) {

				$this->recurring_totals = array (); // Reset for cached carts

				foreach ( WC()->cart->recurring_carts as $cart ) {

					if ( ! $cart->prices_include_tax ) {
						$this->recurring_totals[] = $cart->cart_contents_total;
					} else {
						$this->recurring_totals[] = $cart->cart_contents_total + $cart->tax_total;
					}
				}
			} else {
				return false;
			}
		}

		return $this->recurring_totals;
	}

	/**
	 * Check if payment method is available in checkout based on amount, currency and sequenceType
	 *
	 * @param $filters
	 *
	 * @return bool
	 */
	protected function isAvailableMethodInCheckout( $filters ) {

		$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
		$test_mode       = $settings_helper->isTestModeEnabled();

		try {

			$filters_key   = $filters['amount']['currency'] . '_' . str_replace( '.', '', $filters['amount']['value'] ) . '_' . $filters['sequenceType'];
			$transient_id = Mollie_WC_Plugin::getDataHelper()->getTransientId( 'api_methods_' . ( $test_mode ? 'test' : 'live' ) . '_' . $filters_key );

			$cached = unserialize( get_transient( $transient_id ) );

			if ( $cached && $cached instanceof \Mollie\Api\Resources\MethodCollection ) {
				$methods = $cached;
			}

			if ( empty ( $methods ) ) {

				// Remove existing expired transients
				delete_transient( $transient_id );

				// Get payment methods at Mollie
				$methods = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->methods->all( $filters );

				// Set new transients (as cache)
				set_transient( $transient_id, serialize( $methods ), MINUTE_IN_SECONDS * 5 );

			}

			// Get the ID of the WooCommerce/Mollie payment method
			$woocommerce_method = $this->getMollieMethodId();

			// Set all other payment methods to false, so they can be updated if available
			foreach ( $methods as $method ) {

				if ( $method->id == $woocommerce_method ) {
					return true;
				}
			}
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {

			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not check availability of Mollie payment methods (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );

		}

		return false;
	}

	/**
	 * Get the transaction URL.
	 *
	 * @param  WC_Order $order
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = 'https://www.mollie.com/dashboard/payments/%s';

		return parent::get_transaction_url( $order );
	}

}
