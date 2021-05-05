<?php

use Mollie\Api\Exceptions\ApiException;

abstract class Mollie_WC_Gateway_Abstract extends WC_Payment_Gateway
{
	/**
	 * WooCommerce default statuses
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_ON_HOLD = 'on-hold';
	const STATUS_COMPLETED = 'completed';
	const STATUS_CANCELLED = 'cancelled'; // Mollie uses canceled (US English spelling), WooCommerce and this plugin use cancelled.
	const STATUS_FAILED = 'failed';
	const STATUS_REFUNDED = 'refunded';

    const PAYMENT_METHOD_TYPE_PAYMENT = 'payment';
    const PAYMENT_METHOD_TYPE_ORDER = 'order';

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
	 * @var bool
	 */
	public static $alreadyDisplayedInstructions = false;

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
        wp_register_script('mollie_wc_admin_settings', Mollie_WC_Plugin::getPluginUrl('/public/js/settings.min.js'), array('underscore','jquery'), Mollie_WC_Plugin::PLUGIN_VERSION);
        wp_enqueue_script('mollie_wc_admin_settings');
        global $current_section;

        wp_localize_script(
                'mollie_wc_admin_settings',
                'mollieSettingsData',
                [
                        'current_section'=>$current_section
                ]
        );
        $settingsHelper = Mollie_WC_Plugin::getSettingsHelper();
        $this->form_fields = $settingsHelper->gatewayFormFields(
                $this->getDefaultTitle(),
                $this->getDefaultDescription(),
                $this->paymentConfirmationAfterCoupleOfDays()
        );

    }

    public function init_settings()
    {
        parent::init_settings();
        if(is_admin()){
            global $current_section;
            wp_register_script(
                    'mollie_wc_gateway_settings',
                    Mollie_WC_Plugin::getPluginUrl(
                            '/public/js/gatewaySettings.min.js'
                    ),
                    ['underscore', 'jquery'],
                    Mollie_WC_Plugin::PLUGIN_VERSION
            );

            wp_enqueue_script('mollie_wc_gateway_settings');
            wp_enqueue_style('mollie-gateway-icons');
            $settingsName = "{$current_section}_settings";
            $gatewaySettings = get_option($settingsName, false);
            $message = __('No custom logo selected', 'mollie-payments-for-woocommerce');
            $isEnabled = false;
            if($gatewaySettings && isset($gatewaySettings['enable_custom_logo'])){
                $isEnabled = $gatewaySettings['enable_custom_logo'] === 'yes';
            }
            $uploadFieldName = "{$current_section}_upload_logo";
            $enabledFieldName = "{$current_section}_enable_custom_logo";
            $gatewayIconUrl = '';
            if($gatewaySettings && isset($gatewaySettings['iconFileUrl'])){
                $gatewayIconUrl = $gatewaySettings['iconFileUrl'];
            }

            wp_localize_script(
                    'mollie_wc_gateway_settings',
                    'gatewaySettingsData',
                    [
                            'isEnabledIcon' => $isEnabled,
                            'uploadFieldName' => $uploadFieldName,
                            'enableFieldName' => $enabledFieldName,
                            'iconUrl' => $gatewayIconUrl,
                            'message'=>$message
                    ]
            );
        }


    }
    /**
     * Save settings
     *
     * @since 1.0
     */
    /**
     * Save options in admin.
     */
    public function process_admin_options()
    {
        if (isset($_POST['save']) ) {
            $this->processAdminOptionCustomLogo();
            $this->processAdminOptionSurcharge();
        }
        parent::process_admin_options();
    }

    /**
     * @return string
     */
    public function getIconUrl()
    {
        return $this->iconFactory()->svgUrlForPaymentMethod(
            $this->getMollieMethodId()
        );
    }

    protected function _initIcon ()
    {
        if ($this->display_logo)
        {
            $default_icon = $this->getIconUrl();
            $this->icon   = apply_filters($this->id . '_icon_url', $default_icon);
        }
    }
    public function get_icon() {
        $output = $this->icon ? $this->icon : '';

        return apply_filters( 'woocommerce_gateway_icon', $output, $this->id );
    }

    protected function _initDescription ()
    {
        $description = $this->buildDescriptionWithSurcharge();

        $this->description = $description;
    }

    public function admin_options()
    {
        if (!$this->enabled && count($this->errors)) {
            echo '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'mollie-payments-for-woocommerce') . '</strong>: '
                    . implode('<br/>', $this->errors)
                    . '</p></div>';

            return;
        }

        $html = '';
        foreach ($this->get_form_fields() as $k => $v) {
            $type = $this->get_field_type($v);

            if ($type === 'multi_select_countries') {
                $html .= $this->multiSelectCountry();
            } else {
                if (method_exists($this, 'generate_' . $type . '_html')) {
                    $html .= $this->{'generate_' . $type . '_html'}($k, $v);
                } else {
                    $html .= $this->generate_text_html($k, $v);
                }
            }
        }

        echo '<h2>' . esc_html($this->get_method_title());
        wc_back_link(__('Return to payments', 'mollie-payments-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        echo '</h2>';
        echo wp_kses_post(wpautop($this->get_method_description()));
        echo '<table class="form-table">'
                .
                $html
                .
                '</table>';
    }

    public function multiSelectCountry()
    {

        $selections = (array)$this->get_option('allowed_countries', []);
        $gatewayId = $this->getMollieMethodId();
        $id = 'mollie_wc_gateway_'.$gatewayId.'_allowed_countries';
        $title = __('Sell to specific countries', 'mollie-payments-for-woocommerce');
        $description = '<span class="description">' . wp_kses_post($this->get_option('description', '')) . '</span>';
        $countries = WC()->countries->countries;
        asort($countries);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?> </label>
            </th>
            <td class="forminp">
                <select multiple="multiple" name="<?php echo esc_attr($id); ?>[]" style="width:350px"
                        data-placeholder="<?php esc_attr_e('Choose countries&hellip;', 'mollie-payments-for-woocommerce'); ?>"
                        aria-label="<?php esc_attr_e('Country', 'mollie-payments-for-woocommerce'); ?>" class="wc-enhanced-select">
                    <?php
                    if (!empty($countries)) {
                        foreach ($countries as $key => $val) {
                            echo '<option value="' . esc_attr($key) . '"' . wc_selected($key, $selections) . '>' . esc_html($val) . '</option>';
                        }
                    }
                    ?>
                </select> <?php echo ($description) ? $description : ''; ?> <br/><a class="select_all button"
                                                                                    href="#"><?php esc_html_e('Select all', 'mollie-payments-for-woocommerce'); ?></a>
                <a class="select_none button" href="#"><?php esc_html_e('Select none', 'mollie-payments-for-woocommerce'); ?></a>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Validates the multiselect country field.
     * Overrides the one called by get_field_value() on WooCommerce abstract-wc-settings-api.php
     *
     * @param $key
     * @param $value
     * @return array|string
     */
    public function validate_multi_select_countries_field($key, $value)
    {
        return is_array($value) ? array_map('wc_clean', array_map('stripslashes', $value)) : '';
    }


    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    protected function isValidForUse()
    {

    	//
    	// Abort if this is not the WooCommerce settings page
		//

	    // Return if function get_current_screen() is not defined
	    if ( ! function_exists( 'get_current_screen' ) ) {
		    return true;
	    }

	    // Try getting get_current_screen()
	    $current_screen = get_current_screen();

	    // Return if get_current_screen() isn't set
	    if ( ! $current_screen ) {
		    return true;
	    }

	    // Remove old MisterCash (only) from WooCommerce Payment settings
	    if ( is_admin() && ! empty( $current_screen->base ) && $current_screen->base == 'woocommerce_page_wc-settings' ) {

		    $settings = Mollie_WC_Plugin::getSettingsHelper();

		    if ( ! $this->isValidApiKeyProvided() ) {
			    $test_mode = $settings->isTestModeEnabled();

			    $this->errors[] = ( $test_mode ? __( 'Test mode enabled.', 'mollie-payments-for-woocommerce' ) . ' ' : '' ) . sprintf(
				    /* translators: The surrounding %s's Will be replaced by a link to the global setting page */
					    __( 'No API key provided. Please %sset you Mollie API key%s first.', 'mollie-payments-for-woocommerce' ),
					    '<a href="' . $settings->getGlobalSettingsUrl() . '">',
					    '</a>'
				    );

			    return false;
		    }

		    // This should be simpler, check for specific payment method in settings, not on all pages
		    if ( null === $this->getMollieMethod() ) {
			    $this->errors[] = sprintf(
			    /* translators: Placeholder 1: payment method title. The surrounding %s's Will be replaced by a link to the Mollie profile */
				    __( '%s not enabled in your Mollie profile. You can enable it by editing your %sMollie profile%s.', 'mollie-payments-for-woocommerce' ),
				    $this->getDefaultTitle(),
				    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
				    '</a>'
			    );

			    return false;
		    }

		    if ( ! $this->isCurrencySupported() ) {
			    $this->errors[] = sprintf(
			    /* translators: Placeholder 1: WooCommerce currency, placeholder 2: Supported Mollie currencies */
				    __( 'Current shop currency %s not supported by Mollie. Read more about %ssupported currencies and payment methods.%s ', 'mollie-payments-for-woocommerce' ),
				    get_woocommerce_currency(),
				    '<a href="https://help.mollie.com/hc/en-us/articles/360003980013-Which-currencies-are-supported-and-what-is-the-settlement-currency-" target="_blank">',
				    '</a>'
			    );

			    return false;
		    }
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
		if ( WC()->cart && $this->get_order_total() > 0 ) {

			// Check the current (normal) order total
			$order_total = $this->get_order_total();

			// Get the correct currency for this payment or order
			// On order-pay page, order is already created and has an order currency
			// On checkout, order is not created, use get_woocommerce_currency
			global $wp;
			if ( ! empty( $wp->query_vars['order-pay'] ) ) {
				$order_id = $wp->query_vars['order-pay'];
				$order    = wc_get_order( $order_id );

				$currency = Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order );
			} else {
                $currency = get_woocommerce_currency();
            }

			global $woocommerce;
            $billing_country = WC()->customer->get_billing_country();
            $billing_country = apply_filters(
                Mollie_WC_Plugin::PLUGIN_ID . '_is_available_billing_country_for_payment_gateways',
                $billing_country
            );

            // Get current locale for this user
            $payment_locale = Mollie_WC_Plugin::getSettingsHelper()->getPaymentLocale();

            try {
                $filters = $this->getFilters(
                    $currency,
                    $order_total,
                    $payment_locale,
                    $billing_country
                );
            } catch (InvalidArgumentException $exception) {
                Mollie_WC_Plugin::debug($exception->getMessage());
                return false;
            }

            // For regular payments, check available payment methods, but ignore SSD gateway (not shown in checkout)
            $status = ($this->id !== 'mollie_wc_gateway_directdebit') ? $this->isAvailableMethodInCheckout($filters) : false;
            $allowedCountries = $this->get_option('allowed_countries', []);
            //if no country is selected then this does not apply
            $bCountryIsAllowed = empty($allowedCountries) ? true : in_array($billing_country, $allowedCountries);
            if (!$bCountryIsAllowed) {
                $status = false;
            }
            // Do extra checks if WooCommerce Subscriptions is installed
            if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin')) {

                // Check recurring totals against recurring payment methods for future renewal payments
                $recurring_totals = $this->get_recurring_total();

				// See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
				$accept_manual_renewals = ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no' ) ) ? true : false;
				$supports_subscriptions = $this->supports( 'subscriptions' );

				if ( $accept_manual_renewals !== true && $supports_subscriptions ) {

					if ( ! empty( $recurring_totals ) ) {
						foreach ( $recurring_totals as $recurring_total ) {

							// First check recurring payment methods CC and SDD
							$filters = array (
								'amount'       => array (
									'currency' => $currency,
									'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $recurring_total, $currency )
								),
								'resource'       => 'orders',
								'billingCountry' => $billing_country,
								'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_RECURRING
							);

                            $payment_locale and $filters['locale'] = $payment_locale;

							$status = $this->isAvailableMethodInCheckout( $filters );

						}

						// Check available first payment methods with today's order total, but ignore SSD gateway (not shown in checkout)
						if ( $this->id !== 'mollie_wc_gateway_directdebit' ) {
							$filters = array (
								'amount'       => array (
									'currency' => $currency,
									'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order_total, $currency )
								),
								'resource'       => 'orders',
                                'locale'         => $payment_locale,
								'billingCountry' => $billing_country,
								'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_FIRST
							);

							$status = $this->isAvailableMethodInCheckout( $filters );
						}
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
	    $dataHelper = Mollie_WC_Plugin::getDataHelper();
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Mollie_WC_Plugin::debug( $this->id . ': Could not process payment, order ' . $order_id . ' not found.' );

			Mollie_WC_Plugin::addNotice( sprintf( __( 'Could not load order %s', 'mollie-payments-for-woocommerce' ), $order_id ), 'error' );

			return array ( 'result' => 'failure' );
		}

		$orderId = $order->get_id();
        mollieWooCommerceDebug( "{$this->id}: Start process_payment for order {$orderId}", true );

		$initial_order_status = $this->getInitialOrderStatus();

		// Overwrite plugin-wide
		$initial_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status );

		// Overwrite gateway-wide
		$initial_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status );

		$settings_helper = Mollie_WC_Plugin::getSettingsHelper();

		// Is test mode enabled?
		$test_mode          = $settings_helper->isTestModeEnabled();
		$customer_id        = $this->getUserMollieCustomerId( $order, $test_mode );

		//
		// PROCESS SUBSCRIPTION SWITCH - If this is a subscription switch and customer has a valid mandate, process the order internally
		//
		if ( ( '0.00' === $order->get_total() ) && ( Mollie_WC_Plugin::getDataHelper()->isWcSubscription($order_id ) == true ) &&
		     0 != $order->get_user_id() && ( wcs_order_contains_switch( $order ) )
		) {

            try {
                $paymentObject = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject(
                    self::PAYMENT_METHOD_TYPE_PAYMENT
                );
                $paymentRequestData = $paymentObject->getPaymentRequestData($order, $customer_id);
                $data = array_filter($paymentRequestData);
                $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $order);

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
					throw new Mollie\Api\Exceptions\ApiException( __( 'Failed switching subscriptions, no valid mandate.', 'mollie-payments-for-woocommerce' ) );
				}
			}
			catch ( Mollie\Api\Exceptions\ApiException $e ) {
				if ( $e->getField() ) {
					throw $e;
				}
			}

			return array ( 'result' => 'failure' );

		}

		$molliePaymentType = $this->paymentTypeBasedOnGateway();
        $molliePaymentType = $this->paymentTypeBasedOnProducts($order,$molliePaymentType);
        try {
            $paymentObject = Mollie_WC_Plugin::getPaymentFactoryHelper()
                    ->getPaymentObject(
                            $molliePaymentType
                    );
        } catch (ApiException $exception) {
            Mollie_WC_Plugin::debug($exception->getMessage());
            return array('result' => 'failure');
        }

		//
		// TRY PROCESSING THE PAYMENT AS MOLLIE ORDER OR MOLLIE PAYMENT
		//

		try {
            $paymentObject = $this->processPaymentForMollie(
                    $molliePaymentType,
                    $orderId,
                    $paymentObject,
                    $order,
                    $customer_id,
                    $test_mode
            );

			$this->saveMollieInfo( $order, $paymentObject );

            if ($dataHelper->isSubscription($orderId)) {
                $mandates = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $customer_id )->mandates();
                $mandate = $mandates[0];
                $customerId = $mandate->customerId;
                $mandateId = $mandate->id;
                Mollie_WC_Plugin::debug("Mollie Subscription in the order: customer id {$customerId} and mandate id {$mandateId} ");
                do_action(Mollie_WC_Plugin::PLUGIN_ID . '_after_mandate_created', $paymentObject, $order, $customerId, $mandateId);
            }

			do_action( Mollie_WC_Plugin::PLUGIN_ID . '_payment_created', $paymentObject, $order );
            Mollie_WC_Plugin::debug( $this->id . ': Mollie payment object ' . $paymentObject->id . ' (' . $paymentObject->mode . ') created for order ' . $orderId );

			// Update initial order status for payment methods where the payment status will be delivered after a couple of days.
			// See: https://www.mollie.com/nl/docs/status#expiry-times-per-payment-method
			// Status is only updated if the new status is not the same as the default order status (pending)
			if ( ( $paymentObject->method == 'banktransfer' ) || ( $paymentObject->method == 'directdebit' ) ) {

				// Don't change the status of the order if it's Partially Paid
				// This adds support for WooCommerce Deposits (by Webtomizer)
				// See https://github.com/mollie/WooCommerce/issues/138

				$order_status = $order->get_status();

				if ( $order_status != 'wc-partially-paid ' ) {

					$this->updateOrderStatus(
						$order,
						$initial_order_status,
						__( 'Awaiting payment confirmation.', 'mollie-payments-for-woocommerce' ) . "\n"
					);

				}
			}

			$paymentMethodTitle = $this->getPaymentMethodTitle($paymentObject);

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
				__( '%s payment started (%s).', 'mollie-payments-for-woocommerce' ),
				$paymentMethodTitle,
				$paymentObject->id . ( $paymentObject->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

            mollieWooCommerceDebug( "For order " . $orderId . " redirect user to Mollie Checkout URL: " . $paymentObject->getCheckoutUrl() );


			return array (
				'result'   => 'success',
				'redirect' => $this->getProcessPaymentRedirect( $order, $paymentObject ),
			);
		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {
            Mollie_WC_Plugin::debug( $this->id . ': Failed to create Mollie payment object for order ' . $orderId . ': ' . $e->getMessage() );

			/* translators: Placeholder 1: Payment method title */
			$message = sprintf( __( 'Could not create %s payment.', 'mollie-payments-for-woocommerce' ), $this->title );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message .= 'hii ' . $e->getMessage();
			}

			Mollie_WC_Plugin::addNotice( $message, 'error' );
		}

		return array ( 'result' => 'failure' );
	}

	/**
	 * @param $order
	 * @param $payment
	 */
	protected function saveMollieInfo( $order, $payment ) {
        // Get correct Mollie Payment Object
        $payment_object = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject( $payment );

        // Set active Mollie payment
        $payment_object->setActiveMolliePayment( $order->get_id() );

        // Get Mollie Customer ID
        $mollie_customer_id = $payment_object->getMollieCustomerIdFromPaymentObject( $payment_object->data->id );

        // Set Mollie customer
        Mollie_WC_Plugin::getDataHelper()->setUserMollieCustomerId( $order->get_customer_id(), $mollie_customer_id );
	}

    /**
     * @param $order
     * @param $test_mode
     * @return null|string
     */
    protected function getUserMollieCustomerId($order, $test_mode)
    {
	    $order_customer_id = $order->get_customer_id();

	    return  Mollie_WC_Plugin::getDataHelper()->getUserMollieCustomerId($order_customer_id, $test_mode);
    }

	/**
	 * Redirect location after successfully completing process_payment
	 *
	 * @param WC_Order                                            $order
	 * @param \Mollie_WC_Payment_Order|\Mollie_WC_Payment_Payment $payment_object
	 *
	 * @return string
	 */
    protected function getProcessPaymentRedirect(WC_Order $order, $payment_object )
    {
        /*
         * Redirect to payment URL
         */
        return $payment_object->getCheckoutUrl();
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
        $order       = wc_get_order($order_id);

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
            Mollie_WC_Plugin::debug(__METHOD__ . ': No payment object ID provided.', true);
            return;
        }

	    $payment_object_id = sanitize_text_field( $_POST['id'] );
        $test_mode  = $data_helper->getActiveMolliePaymentMode($order_id) == 'test';

        // Load the payment from Mollie, do not use cache
        try {
            $payment_object = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject(
                $payment_object_id
            );
        } catch (ApiException $exception) {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug($exception->getMessage());
            return;
        }

	    $payment = $payment_object->getPaymentObject( $payment_object->data, $test_mode, $use_cache = false );

        // Payment not found
        if (!$payment)
        {
            Mollie_WC_Plugin::setHttpResponseCode(404);
            Mollie_WC_Plugin::debug(__METHOD__ . ": payment object $payment_object_id not found.", true);
            return;
        }

        if ($order_id != $payment->metadata->order_id)
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id");
            return;
        }

        // Log a message that webhook was called, doesn't mean the payment is actually processed
        Mollie_WC_Plugin::debug($this->id . ": Mollie payment object {$payment->id} (" . $payment->mode . ") webhook call for order {$order->get_id()}.", true);


        // Order does not need a payment
	    if ( ! $this->orderNeedsPayment( $order ) ) {

	    	// TODO David: move to payment object?
		    // Add a debug message that order was already paid for
		    $this->handlePaidOrderWebhook( $order, $payment );

		    // Check and process a possible refund or chargeback
		    $this->processRefunds( $order, $payment );
		    $this->processChargebacks( $order, $payment );

		    return;
	    }

	    // Get payment method title
	    $payment_method_title = $this->getPaymentMethodTitle( $payment );

	    // Create the method name based on the payment status
        $method_name = 'onWebhook' . ucfirst($payment->status);

        if (method_exists($payment_object, $method_name))
        {
            $payment_object->{$method_name}($order, $payment, $payment_method_title);
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

        $order    = wc_get_order( $order );
        $order_id = $order->get_id();

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $this->id . ": Order $order_id does not need a payment by Mollie (payment {$payment->id}).", true );

	}

	/**
	 * @param WC_Order                                                $order
	 * @param Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order $payment
	 */
    protected function processRefunds(WC_Order $order, $payment)
    {
        $orderId = $order->get_id();

        // Debug log ID (order id/payment id)
        $logId = "order {$orderId} / payment{$payment->id}";

        // Add message to log
        Mollie_WC_Plugin::debug(__METHOD__ . " called for {$logId}");

        // Make sure there are refunds to process at all
        if (empty($payment->_links->refunds)) {
            Mollie_WC_Plugin::debug(
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
            $refundIds = array();
            foreach ($refunds as $refund) {
                $refundIds[] = $refund->id;
            }

            Mollie_WC_Plugin::debug(
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
                $processedRefundIds = array();
            }

            Mollie_WC_Plugin::debug(
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
                Mollie_WC_Plugin::debug(
                    __METHOD__
                    . " Refunds that need to be processed for {$logId}: "
                    . json_encode($refundsToProcess)
                );
            } else {
                // No new refunds, stop processing.
                Mollie_WC_Plugin::debug(
                    __METHOD__ . " No new refunds, stop processing for {$logId}"
                );

                return;
            }

            $dataHelper = Mollie_WC_Plugin::getDataHelper();
            $order = wc_get_order($orderId);

            foreach ($refundsToProcess as $refundToProcess) {
                Mollie_WC_Plugin::debug(
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
            Mollie_WC_Plugin::debug(
                __METHOD__ . " Updated, all processed refunds for {$logId}: "
                . json_encode($processedRefundIds)
            );

            $order->save();
            $this->processUpdateStateRefund($order, $payment);
            Mollie_WC_Plugin::debug(
                __METHOD__ . " Updated state for order {$orderId}"
            );

            do_action(
                Mollie_WC_Plugin::PLUGIN_ID . '_refunds_processed',
                $payment,
                $order
            );

            return;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            Mollie_WC_Plugin::debug(
                __FUNCTION__
                . " : Could not load refunds for {$payment->id}: {$e->getMessage()}"
                . ' (' . get_class($e) . ')'
            );
        }
    }

	/**
	 * @param WC_Order                                                $order
	 * @param Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order $payment
	 */
    protected function processChargebacks(WC_Order $order, $payment)
    {
        $orderId = $order->get_id();

        // Debug log ID (order id/payment id)
        $logId = "order {$orderId} / payment {$payment->id}";

        // Add message to log
        Mollie_WC_Plugin::debug(__METHOD__ . " called for {$logId}");

        // Make sure there are chargebacks to process at all
        if (empty($payment->_links->chargebacks)) {
            Mollie_WC_Plugin::debug(
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
            $chargebackIds = array();
            foreach ($chargebacks as $chargeback) {
                $chargebackIds[] = $chargeback->id;
            }

            Mollie_WC_Plugin::debug(
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
                $processedChargebackIds = array();
            }

            Mollie_WC_Plugin::debug(
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
                Mollie_WC_Plugin::debug(
                    __METHOD__
                    . " Chargebacks that need to be processed for {$logId}: "
                    . json_encode($chargebacksToProcess)
                );
            } else {
                // No new chargebacks, stop processing.
                Mollie_WC_Plugin::debug(
                    __METHOD__
                    . " No new chargebacks, stop processing for {$logId}"
                );

                return;
            }

            $dataHelper = Mollie_WC_Plugin::getDataHelper();
            $order = wc_get_order($orderId);

            // Update order notes, add message about chargeback
            foreach ($chargebacksToProcess as $chargebackToProcess) {
                Mollie_WC_Plugin::debug(
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
            $newOrderStatus = self::STATUS_ON_HOLD;

            // Overwrite plugin-wide
            $newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold', $newOrderStatus );

            // Overwrite gateway-wide
            $newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . "_order_status_on_hold_{$this->id}", $newOrderStatus );

            $paymentMethodTitle = $this->getPaymentMethodTitle($payment);

            // Update order status for order with charged_back payment, don't restore stock
            $this->updateOrderStatus(
                $order,
                $newOrderStatus,
                sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __(
                        '%s payment charged back via Mollie (%s). You will need to manually review the payment (and adjust product stocks if you use it).',
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
            Mollie_WC_Plugin::debug(
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
                    $subscriptions = array();
                }

                foreach ($subscriptions as $subscription) {
                    $this->updateOrderStatus(
                        $subscription,
                        $newOrderStatus,
                        sprintf(
                        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                            __(
                                '%s payment charged back via Mollie (%s). Subscription status updated, please review (and adjust product stocks if you use it).',
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
                Mollie_WC_Plugin::PLUGIN_ID . '_chargebacks_processed',
                $payment,
                $order
            );

            return;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            Mollie_WC_Plugin::debug(
                __FUNCTION__ . ": Could not load chargebacks for $payment->id: "
                . $e->getMessage() . ' (' . get_class($e) . ')'
            );
        }
    }

    /**
     * @param $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {

    	// TODO David: this needs to be updated, doesn't work in all cases?
        $payment_method_title = '';
        if ($payment->method == $this->getMollieMethodId()){
            $payment_method_title = $this->method_title;
        }
        return $payment_method_title;
    }

	/**
	 * @param WC_Order $order
	 *
	 * @return string
	 */
    public function getReturnRedirectUrlForOrder( WC_Order $order )
    {
        $order_id = $order->get_id();
        $debugLine = __METHOD__ . " {$order_id}: Determine what the redirect URL in WooCommerce should be.";
        mollieWooCommerceDebug($debugLine);
        $hookReturnPaymentStatus = 'success';
        $returnRedirect = $this->get_return_url( $order );
        $failedRedirect = $order->get_checkout_payment_url( false );
        if ( $this->orderNeedsPayment( $order ) ) {

            $hasCancelledMolliePayment = $this->paymentObject()->getCancelledMolliePaymentId( $order_id );

            if ( $hasCancelledMolliePayment ) {

                $settings_helper                 = Mollie_WC_Plugin::getSettingsHelper();
                $order_status_cancelled_payments = $settings_helper->getOrderStatusCancelledPayments();

                // If user set all cancelled payments to also cancel the order,
                // redirect to /checkout/order-received/ with a message about the
                // order being cancelled. Otherwise redirect to /checkout/order-pay/ so
                // customers can try to pay with another payment method.
                if ( $order_status_cancelled_payments == 'cancelled' ) {
                    return $returnRedirect;

                } else {
                    Mollie_WC_Plugin::addNotice(
                            __(
                                    'You have cancelled your payment. Please complete your order with a different payment method.',
                                    'mollie-payments-for-woocommerce'
                            )
                    );

                    // Return to order payment page
                    return $failedRedirect;
                }

                // Return to order payment page
                return $failedRedirect;
            }

            try {
                $payment = $this->activePaymentObject($order_id, false);
                if ( ! $payment->isOpen() && ! $payment->isPending() && ! $payment->isPaid() && ! $payment->isAuthorized() ) {
                    mollieWooCommerceNotice(__('Your payment was not successful. Please complete your order with a different payment method.', 'mollie-payments-for-woocommerce'));
                    // Return to order payment page
                    return $failedRedirect;
                }
                if ($payment->method === "giftcard") {
                    $this->debugGiftcardDetails($payment, $order);
                }

            } catch (UnexpectedValueException $exc) {
                mollieWooCommerceNotice(__('Your payment was not successful. Please complete your order with a different payment method.', 'mollie-payments-for-woocommerce' ));
                $exceptionMessage = $exc->getMessage();
                $debugLine = __METHOD__ . " Problem processing the payment. {$exceptionMessage}";
                mollieWooCommerceDebug($debugLine);
                $hookReturnPaymentStatus = 'failed';
            }
        }
        do_action( Mollie_WC_Plugin::PLUGIN_ID . '_customer_return_payment_' . $hookReturnPaymentStatus, $order );

        /*
         * Return to order received page
         */
        return $returnRedirect;
    }
    /**
     * Retrieve the payment object
     *
     * @return Mollie_WC_Payment_Object
     */
    protected function paymentObject()
    {
        return Mollie_WC_Plugin::getPaymentObject();
    }

    /**
     * Retrieve the active payment object
     *
     * @param $orderId
     * @param $useCache
     * @return Payment
     * @throws UnexpectedValueException
     */
    protected function activePaymentObject($orderId, $useCache)
    {
        $paymentObject = $this->paymentObject();
        $activePaymentObject = $paymentObject->getActiveMolliePayment($orderId, $useCache);

        if ($activePaymentObject === null) {
            throw new UnexpectedValueException(
                "Active Payment Object is not a valid Payment Resource instance. Order ID: {$orderId}"
            );
        }

        return $activePaymentObject;
    }

	/**
	 * Process a refund if supported
	 *
	 * @param int    $order_id
	 * @param float  $amount
	 * @param string $reason
	 *
	 * @return bool|wp_error True or false based on success, or a WP_Error object
	 * @since WooCommerce 2.2
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		// Get the WooCommerce order
		$order = wc_get_order( $order_id );

		// WooCommerce order not found
		if ( ! $order ) {
			$error_message = "Could not find WooCommerce order $order_id.";

			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message);

			return new WP_Error( '1', $error_message );
		}

		// Check if there is a Mollie Payment Order object connected to this WooCommerce order
		$payment_object_id = Mollie_WC_Plugin::getPaymentObject()->getActiveMollieOrderId( $order_id);

		// If there is no Mollie Payment Order object, try getting a Mollie Payment Payment object
		if ( $payment_object_id == null ) {
			$payment_object_id = Mollie_WC_Plugin::getPaymentObject()->getActiveMolliePaymentId( $order_id);
		}

		// Mollie Payment object not found
		if ( ! $payment_object_id ) {

			$error_message = "Can\'t process refund. Could not find Mollie Payment object id for order $order_id.";

			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message );

			return new WP_Error( '1', $error_message );
		}

        try {
            $payment_object = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject(
                $payment_object_id
            );
        } catch (ApiException $exception) {
            $exceptionMessage = $exception->getMessage();
            Mollie_WC_Plugin::debug($exceptionMessage);
            return new WP_Error('error', $exceptionMessage);
        }

		if ( ! $payment_object ) {

			$error_message = "Can\'t process refund. Could not find Mollie Payment object data for order $order_id.";

			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message);

			return new WP_Error( '1', $error_message);
		}

		return $payment_object->refund( $order, $order_id, $payment_object, $amount, $reason );

	}

    /**
     * Output for the order received page.
     */
    public function thankyou_page ($order_id)
    {
        $order = wc_get_order($order_id);

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
	 * @param bool     $plain_text         (default: false)
	 *
	 * @return void
	 */
	public function displayInstructions( WC_Order $order, $admin_instructions = false, $plain_text = false ) {

		if ( ! self::$alreadyDisplayedInstructions ) {
            $order_payment_method = $order->get_payment_method();

			// Invalid gateway
			if ( $this->id !== $order_payment_method ) {
				return;
			}

            $payment = Mollie_WC_Plugin::getPaymentObject()->getActiveMolliePayment( $order->get_id() );

            // Mollie payment not found or invalid gateway
			if ( ! $payment || $payment->method != $this->getMollieMethodId() ) {
				return;
			}

			$instructions = $this->getInstructions( $order, $payment, $admin_instructions, $plain_text );

			if ( ! empty( $instructions ) ) {
				$instructions = wptexturize( $instructions );

				if ( $plain_text ) {
					echo $instructions . PHP_EOL;
				} else {
					echo '<section class="woocommerce-order-details woocommerce-info mollie-instructions" >';
					echo wpautop( $instructions ) . PHP_EOL;
					echo '</section>';
				}
			}
		}
		self::$alreadyDisplayedInstructions = true;

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

                $order_key_db = $order->get_order_key();

				if ( $order_key_db != $order_key ) {
					$order = false;
				}
			}

			if ( $order == false){
				return $title;
			}

			$order = wc_get_order( $order );

            $order_payment_method = $order->get_payment_method();

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
            $payment = Mollie_WC_Plugin::getPaymentObject()->getActiveMolliePayment( $order->get_id() );

			// Mollie payment not found or invalid gateway
			if ( ! $payment || $payment->method != $this->getMollieMethodId() ) {
				return $title;
			}

			if ( $payment->isOpen() ) {

				// Add a message to log and order explaining a payment with status "open", only if it hasn't been added already
				if ( get_post_meta( $order_id, '_mollie_open_status_note', true ) !== '1' ) {

					// Get payment method title
					$payment_method_title = $this->getPaymentMethodTitle( $payment );

					// Add message to log
					Mollie_WC_Plugin::debug( $this->id . ': Customer returned to store, but payment still pending for order #' . $order_id . '. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.' );

					// Add message to order as order note
					$order->add_order_note( sprintf(
					/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
						__( '%s payment still pending (%s) but customer already returned to the store. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.', 'mollie-payments-for-woocommerce' ),
						$payment_method_title,
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

        $order_payment_method = $order->get_payment_method();

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

        $order_id = $order->get_id();

		// Check whether the order is processed and paid via another gateway
		if ( $this->isOrderPaidByOtherGateway( $order ) ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' ' . $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: no, previously processed by other (non-Mollie) gateway.', true );

			return false;
		}

		// Check whether the order is processed and paid via Mollie
		if ( ! $this->isOrderPaidAndProcessed( $order ) ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' ' . $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, order not previously processed by Mollie gateway.', true );

			return true;
		}

		if ( $order->needs_payment() ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' ' . $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, WooCommerce thinks order needs payment.', true );

			return true;
		}

		// Has initial order status 'on-hold'
		if ( $this->getInitialOrderStatus() === self::STATUS_ON_HOLD && $order->has_status( self::STATUS_ON_HOLD ) ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' ' . $this->id . ': Order ' . $order_id . ' orderNeedsPayment check: yes, has status On-Hold. ', true );

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
			$this->getMollieMethodId(),
            $test_mode
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
     * Get the url to return to on Mollie return
     * saves the return redirect and failed redirect, so we save the page language in case there is one set
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with order id and key as params
     */
    public function getReturnUrl (WC_Order $order)
    {
        $returnUrl = $this->get_return_url($order);
        $returnUrl = untrailingslashit($returnUrl);
        $returnUrl = $this->asciiDomainName($returnUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $onMollieReturn = 'onMollieReturn';
        $returnUrl = $this->appendOrderArgumentsToUrl(
                $orderId,
                $orderKey,
                $returnUrl,
                $onMollieReturn
        );
	    $returnUrl = untrailingslashit($returnUrl);

        mollieWooCommerceDebug("{$this->id} : Order {$orderId} returnUrl: {$returnUrl}", true);

        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_return_url', $returnUrl, $order);
    }

    /**
     * Get the webhook url
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with gateway and order id and key as params
     */
    public function getWebhookUrl (WC_Order $order)
    {
        $webhookUrl = WC()->api_request_url(strtolower(get_class($this)));
        $webhookUrl = untrailingslashit($webhookUrl);
        $webhookUrl = $this->asciiDomainName($webhookUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $webhookUrl = $this->appendOrderArgumentsToUrl(
                $orderId,
                $orderKey,
                $webhookUrl
        );
        $webhookUrl = untrailingslashit($webhookUrl);

        mollieWooCommerceDebug("{$this->id} : Order {$orderId} webhookUrl: {$webhookUrl}", true);

        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_webhook_url', $webhookUrl, $order);
    }

    /**
     * @return string|NULL
     */
    public function getSelectedIssuer ()
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

        return !empty($api_key) && preg_match('/^(live|test)_\w{30,}$/', $api_key);
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

		$data_helper = Mollie_WC_Plugin::getDataHelper();
		$methods     = $data_helper->getApiPaymentMethods( $test_mode, $use_cache = true, $filters);

		// Get the ID of the WooCommerce/Mollie payment method
		$woocommerce_method = $this->getMollieMethodId();

		// Set all other payment methods to false, so they can be updated if available
		foreach ( $methods as $method ) {

			if ( $method['id'] == $woocommerce_method ) {
				return true;
			}
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

        $resource = ($order->get_meta( '_mollie_order_id', true )) ? 'orders' : 'payments';

		$this->view_transaction_url = 'https://www.mollie.com/dashboard/' . $resource . '/%s';

		return parent::get_transaction_url( $order );
	}

    protected function hasFieldsIfMollieComponentsIsEnabled()
    {
        $this->has_fields = $this->isMollieComponentsEnabled() ? true : false;
    }

    protected function includeMollieComponentsFields()
    {
        $fields = include Mollie_WC_Plugin::getPluginPath(
            '/inc/settings/mollie_components_enabler.php'
        );

        $this->form_fields = array_merge($this->form_fields, $fields);
    }

    protected function mollieComponentsFields()
    {
        if (!$this->isMollieComponentsEnabled()) {
            return;
        }

        ?>
        <div class="mollie-components"></div>
        <p class="mollie-components-description">
            <?php
            printf(
                    __(esc_html('%1$s Secure payments provided by %2$s'),
                       'mollie-payments-for-woocommerce'),
                    $this->lockIcon(),
                    $this->mollieLogo()
            );
            ?>
        </p>
        <?php
    }

    protected function isMollieComponentsEnabled()
    {
        $option = isset($this->settings['mollie_components_enabled'])
            ? $this->settings['mollie_components_enabled']
            : 'no';

        $option = mollieWooCommerceStringToBoolOption($option);

        return $option;
    }

    protected function lockIcon()
    {
        return file_get_contents(
            Mollie_WC_Plugin::getPluginPath('public/images/lock-icon.svg')
        );
    }

    protected function mollieLogo()
    {
        return file_get_contents(
            Mollie_WC_Plugin::getPluginPath('public/images/mollie-logo.svg')
        );
    }

    /**
     * Singleton of the class that handles icons (API/fallback)
     * @return Mollie_WC_Helper_PaymentMethodsIconUrl|null
     */
    protected function iconFactory()
    {
        static $factory = null;
        if ($factory === null){
            $factory = new Mollie_WC_Helper_PaymentMethodsIconUrl();
        }

        return $factory;
    }

    /**
     * @param $order_id
     * @param $order_key
     * @param $webhook_url
     * @param string $filterFlag
     *
     * @return string
     */
    protected function appendOrderArgumentsToUrl($order_id, $order_key, $webhook_url, $filterFlag='')
    {
        $webhook_url = add_query_arg(
                array(
                        'order_id' => $order_id,
                        'key' => $order_key,
                        'filter_flag' => $filterFlag
                ),
                $webhook_url
        );
        return $webhook_url;
    }

    /**
     * @param Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order $payment
     *
     * @return bool
     */
    protected function isPartialRefund($payment)
    {
        return (float)($payment->amount->value - $payment->amountRefunded->value) !== 0.0;
    }

    /**
     * @param WC_Order                                                $order
     * @param Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order $payment
     */
    protected function processUpdateStateRefund(WC_Order $order, $payment)
    {
        if (!$this->isPartialRefund($payment)) {
            $this->updateStateRefund(
                    $order,
                    $payment,
                    self::STATUS_REFUNDED,
                    '_order_status_refunded'
            );
        }
    }

    /**
     * @param WC_Order                                                $order
     * @param Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order $payment
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
            Mollie_WC_Plugin::PLUGIN_ID . $refundType,
            $newOrderStatus
        );

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters(
            Mollie_WC_Plugin::PLUGIN_ID . $refundType . $this->id,
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

    /**
     * Method to print the giftcard payment details on debug and order note
     *
     * @param Mollie\Api\Resources\Payment $payment
     * @param WC_Order                     $order
     *
     */
    protected function debugGiftcardDetails(
            Mollie\Api\Resources\Payment $payment,
            WC_Order $order
    ) {
        $details = $payment->details;
        if (!$details) {
            return;
        }
        $orderNoteLine = "";
        foreach ($details->giftcards as $giftcard) {
            $orderNoteLine .= sprintf(
                    esc_html_x(
                            'Mollie - Giftcard details: %1$s %2$s %3$s.',
                            'Placeholder 1: giftcard issuer, Placeholder 2: amount value, Placeholder 3: currency',
                            'mollie-payments-for-woocommerce'
                    ),
                    $giftcard->issuer,
                    $giftcard->amount->value,
                    $giftcard->amount->currency
            );
        }
        if ($details->remainderMethod) {
            $orderNoteLine .= sprintf(
                esc_html_x(
                    ' Remainder: %1$s %2$s %3$s.',
                    'Placeholder 1: remainder method, Placeholder 2: amount value, Placeholder 3: currency',
                    'mollie-payments-for-woocommerce'
                ),
                $details->remainderMethod,
                $details->remainderAmount->value,
                $details->remainderAmount->currency
            );
        }

        $order->add_order_note($orderNoteLine);
    }

    /**
     * Returns a list of filters, ensuring that the values are valid.
     * @param $currency
     * @param $orderTotal
     * @param $paymentLocale
     * @param $billingCountry
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getFilters($currency, $orderTotal, $paymentLocale, $billingCountry)
    {
        $amountValue = $this->getAmountValue($orderTotal, $currency);
        if ($amountValue <= 0) {
            throw new InvalidArgumentException(sprintf('Amount %s is not valid.', $amountValue));
        }

        // Check if currency is in ISO 4217 alpha-3 format (ex: EUR)
        if (!preg_match('/^[a-zA-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(sprintf('Currency %s is not valid.', $currency));
        }

        // Check if billing country is in ISO 3166-1 alpha-2 format (ex: NL)
        if (!preg_match('/^[a-zA-Z]{2}$/', $billingCountry)) {
            throw new InvalidArgumentException(sprintf('Billing Country %s is not valid.', $billingCountry));
        }

        return [
            'amount' => [
                'currency' => $currency,
                'value' => $amountValue,
            ],
            'locale' => $paymentLocale,
            'billingCountry' => $billingCountry,
            'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_ONEOFF,
            'resource' => 'orders',
        ];
    }

    /**
     * @param $order_total
     * @param $currency
     * @return int
     */
    protected function getAmountValue($order_total, $currency)
    {
        return Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue(
            $order_total,
            $currency
        );
    }

    /**
     * CHECK WOOCOMMERCE PRODUCTS
     * Make sure all cart items are real WooCommerce products,
     * not removed products or virtual ones (by WooCommerce Events Manager etc).
     * If products are virtual, use Payments API instead of Orders API
     *
     * @param WC_Order $order
     *
     * @param  string  $molliePaymentType
     *
     * @return string
     */
    protected function paymentTypeBasedOnProducts(WC_Order $order, $molliePaymentType)
    {
        foreach ($order->get_items() as $cart_item) {
            if ($cart_item['quantity']) {
                do_action(
                        Mollie_WC_Plugin::PLUGIN_ID
                        . '_orderlines_process_items_before_getting_product_id',
                        $cart_item
                );

                if ($cart_item['variation_id']) {
                    $product = wc_get_product($cart_item['variation_id']);
                } else {
                    $product = wc_get_product($cart_item['product_id']);
                }

                if ($product == false) {
                    $molliePaymentType = self::PAYMENT_METHOD_TYPE_PAYMENT;
                    do_action(
                            Mollie_WC_Plugin::PLUGIN_ID
                            . '_orderlines_process_items_after_processing_item',
                            $cart_item
                    );
                    break;
                }
                do_action(
                        Mollie_WC_Plugin::PLUGIN_ID
                        . '_orderlines_process_items_after_processing_item',
                        $cart_item
                );
            }
        }
        return $molliePaymentType;
    }

    /**
     * @param Mollie_WC_Payment_Order $paymentObject
     * @param WC_Order                $order
     * @param                         $customer_id
     * @param                         $test_mode
     *
     * @return array
     * @throws ApiException
     */
    protected function processAsMollieOrder(
            Mollie_WC_Payment_Order $paymentObject,
            WC_Order $order,
            $customer_id,
            $test_mode
    ) {
        $molliePaymentType = self::PAYMENT_METHOD_TYPE_ORDER;
        $paymentRequestData = $paymentObject->getPaymentRequestData(
                $order,
                $customer_id
        );

        $data = array_filter($paymentRequestData);

        $data = apply_filters(
                'woocommerce_' . $this->id . '_args',
                $data,
                $order
        );

        do_action(
                Mollie_WC_Plugin::PLUGIN_ID . '_create_payment',
                $data,
                $order
        );

        // Create Mollie payment with customer id.
        try {
            Mollie_WC_Plugin::debug(
                    'Creating payment object: type Order, first try creating a Mollie Order.'
            );

            // Only enable this for hardcore debugging!
            $apiCallLog = [
                    'amount' => isset($data['amount']) ? $data['amount'] : '',
                    'redirectUrl' => isset($data['redirectUrl'])
                            ? $data['redirectUrl'] : '',
                    'webhookUrl' => isset($data['webhookUrl'])
                            ? $data['webhookUrl'] : '',
                    'method' => isset($data['method']) ? $data['method'] : '',
                    'payment' => isset($data['payment']) ? $data['payment']
                            : '',
                    'locale' => isset($data['locale']) ? $data['locale'] : '',
                    'metadata' => isset($data['metadata']) ? $data['metadata']
                            : '',
                    'orderNumber' => isset($data['orderNumber'])
                            ? $data['orderNumber'] : ''
            ];

            mollieWooCommerceDebug($apiCallLog);
            $paymentOrder = $paymentObject;
            $paymentObject = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->create( $data );
            $settingsHelper = Mollie_WC_Plugin::getSettingsHelper();
            if($settingsHelper->getOrderStatusCancelledPayments() == 'cancelled'){
                $orderId = $order->get_id();
                $orderWithPayments = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $paymentObject->id, [ "embed" => "payments" ] );
                $paymentOrder->updatePaymentDataWithOrderData($orderWithPayments, $orderId);
            }
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            // Don't try to create a Mollie Payment for Klarna payment methods
            $order_payment_method = $order->get_payment_method();

            if ($order_payment_method == 'mollie_wc_gateway_klarnapaylater'
                    || $order_payment_method == 'mollie_wc_gateway_sliceit'
            ) {
                Mollie_WC_Plugin::debug(
                        'Creating payment object: type Order, failed for Klarna payment, stopping process.'
                );
                throw $e;
            }

            Mollie_WC_Plugin::debug(
                    'Creating payment object: type Order, first try failed: '
                    . $e->getMessage()
            );

            // Unset missing customer ID
            unset($data['payment']['customerId']);

            try {
                if ($e->getField() !== 'payment.customerId') {
                    Mollie_WC_Plugin::debug(
                            'Creating payment object: type Order, did not fail because of incorrect customerId, so trying Payment now.'
                    );
                    throw $e;
                }

                // Retry without customer id.
                Mollie_WC_Plugin::debug(
                        'Creating payment object: type Order, second try, creating a Mollie Order without a customerId.'
                );
                $paymentObject = Mollie_WC_Plugin::getApiHelper()->getApiClient(
                        $test_mode
                )->orders->create($data);
            } catch (Mollie\Api\Exceptions\ApiException $e) {
                // Set Mollie payment type to payment, when creating a Mollie Order has failed
                $molliePaymentType = self::PAYMENT_METHOD_TYPE_PAYMENT;
            }
        }
        return array(
                $paymentObject,
                $molliePaymentType
        );
    }

    /**
     * @param WC_Order                $order
     * @param                         $customer_id
     * @param                         $test_mode
     *
     * @return Mollie\Api\Resources\Payment $paymentObject
     * @throws ApiException
     */
    protected function processAsMolliePayment(
            WC_Order $order,
            $customer_id,
            $test_mode
    ) {
        $paymentObject = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject(
                self::PAYMENT_METHOD_TYPE_PAYMENT
        );
        $paymentRequestData = $paymentObject->getPaymentRequestData(
                $order,
                $customer_id
        );

        $data = array_filter($paymentRequestData);

        $data = apply_filters(
                'woocommerce_' . $this->id . '_args',
                $data,
                $order
        );

        try {
            // Only enable this for hardcore debugging!
            $apiCallLog = [
                    'amount' => isset($data['amount']) ? $data['amount'] : '',
                    'description' => isset($data['description'])
                            ? $data['description'] : '',
                    'redirectUrl' => isset($data['redirectUrl'])
                            ? $data['redirectUrl'] : '',
                    'webhookUrl' => isset($data['webhookUrl'])
                            ? $data['webhookUrl'] : '',
                    'method' => isset($data['method']) ? $data['method'] : '',
                    'issuer' => isset($data['issuer']) ? $data['issuer'] : '',
                    'locale' => isset($data['locale']) ? $data['locale'] : '',
                    'dueDate' => isset($data['dueDate']) ? $data['dueDate'] : '',
                    'metadata' => isset($data['metadata']) ? $data['metadata']
                            : ''
            ];

            Mollie_WC_Plugin::debug($apiCallLog);

            // Try as simple payment
            $paymentObject = Mollie_WC_Plugin::getApiHelper()->getApiClient(
                    $test_mode
            )->payments->create($data);
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            $message = $e->getMessage();
            Mollie_WC_Plugin::debug($message);
            throw $e;
        }
        return $paymentObject;
    }

    /**
     * @param                         $molliePaymentType
     * @param                         $orderId
     * @param Mollie_WC_Payment_Order|Mollie_WC_Payment_Payment $paymentObject
     * @param WC_Order                $order
     * @param                         $customer_id
     * @param                         $test_mode
     *
     * @return mixed|\Mollie\Api\Resources\Payment|Mollie_WC_Payment_Order
     * @throws ApiException
     */
    protected function processPaymentForMollie(
            $molliePaymentType,
            $orderId,
            $paymentObject,
            WC_Order $order,
            $customer_id,
            $test_mode
    ) {
        //
        // PROCESS REGULAR PAYMENT AS MOLLIE ORDER
        //
        if ($molliePaymentType == self::PAYMENT_METHOD_TYPE_ORDER) {
            Mollie_WC_Plugin::debug(
                    "{$this->id}: Create Mollie payment object for order {$orderId}",
                    true
            );

            list(
                    $paymentObject,
                    $molliePaymentType
                    )
                    = $this->processAsMollieOrder(
                    $paymentObject,
                    $order,
                    $customer_id,
                    $test_mode
            );
        }

        //
        // PROCESS REGULAR PAYMENT AS MOLLIE PAYMENT
        //

        if ($molliePaymentType === self::PAYMENT_METHOD_TYPE_PAYMENT) {
            Mollie_WC_Plugin::debug(
                    'Creating payment object: type Payment, creating a Payment.'
            );

            $paymentObject = $this->processAsMolliePayment(
                    $order,
                    $customer_id,
                    $test_mode
            );
        }
        return $paymentObject;
    }

    protected function paymentTypeBasedOnGateway()
    {
        $optionName = Mollie_WC_Plugin::PLUGIN_ID . '_' .'api_switch';
        $apiSwitchOption = get_option($optionName);
        $paymentType = $apiSwitchOption? $apiSwitchOption : self::PAYMENT_METHOD_TYPE_ORDER;
        $isBankTransferGateway = $this->id == 'mollie_wc_gateway_banktransfer';
        if($isBankTransferGateway && $this->isExpiredDateSettingActivated()){
            $paymentType = self::PAYMENT_METHOD_TYPE_PAYMENT;
        }

        return $paymentType;
    }

    protected function buildDescriptionWithSurcharge()
    {
        if(!mollieWooCommerceIsCheckoutContext()){

            return $this->get_option('description', '');
        }
        if (!isset($this->settings['payment_surcharge'])
                || $this->settings['payment_surcharge']
                === Mollie_WC_Helper_GatewaySurchargeHandler::NO_FEE
        ){
            return $this->get_option('description', '');
        }

        $surchargeType = $this->settings['payment_surcharge'];
        switch($surchargeType){
            case 'fixed_fee':
                $feeText = $this->name_fixed_fee();
                break;
            case 'percentage':
                $feeText = $this->name_percentage();
                break;
            case 'fixed_fee_percentage':
                $feeText = $this->name_fixed_fee_percentage();
                break;
            default:
                $feeText = false;
        }
        if($feeText){
            $feeLabel = '<span class="mollie-gateway-fee">' . $feeText . '</span>';

            return $this->get_option('description', '') . $feeLabel;
        }
        return $this->get_option('description', '');
    }

    protected function name_fixed_fee()
    {
        if (!isset($this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE])
                || $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE] <= 0) {
            return false;
        }
        $amountFee = $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE];
        $currency = get_woocommerce_currency_symbol();
        return sprintf(__(" +%1s%2s Fee", 'mollie-payments-for-woocommerce'), $amountFee, $currency);
    }

    protected function name_percentage()
    {
        if(!isset($this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE])
                || $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE] <= 0){
            return false;
        }
        $amountFee = $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE];
        return sprintf(__(' +%1s%% Fee', 'mollie-payments-for-woocommerce'), $amountFee);
    }

    protected function name_fixed_fee_percentage()
    {
        if (!isset($this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE])
                || !isset($this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE])
                || $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE] == ''
                || $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE] == ''
                || $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE] <= 0
                || $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE] <= 0
        ) {
            return false;
        }
        $amountFix = $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE];
        $currency = get_woocommerce_currency_symbol();
        $amountPercent = $this->settings[Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE];
        return sprintf(__(" +%1s%2s + %3s%% Fee", 'mollie-payments-for-woocommerce'), $amountFix, $currency, $amountPercent);
    }

    protected function processAdminOptionCustomLogo()
    {
        $mollieUploadDirectory = trailingslashit(wp_upload_dir()['basedir'])
                . 'mollie-uploads/' . $this->id;
        wp_mkdir_p($mollieUploadDirectory);
        $targetLocation = $mollieUploadDirectory . '/';
        $fileOptionName = $this->id . '_upload_logo';
        $enabledLogoOptionName = $this->id . '_enable_custom_logo';
        $gatewaySettings = get_option("{$this->id}_settings", []);
        if (!isset($_POST[$enabledLogoOptionName])) {
            $gatewaySettings["iconFileUrl"] = null;
            $gatewaySettings["iconFilePath"] = null;
            update_option("{$this->id}_settings", $gatewaySettings);
        }
        if (isset($_POST[$enabledLogoOptionName])
                && isset($_FILES[$fileOptionName])
                && $_FILES[$fileOptionName]['size'] > 0
        ) {
            if ($_FILES[$fileOptionName]['size'] <= 500000) {
                $fileName = preg_replace(
                        '/\s+/',
                        '_',
                        $_FILES[$fileOptionName]['name']
                );
                $tempName = $_FILES[$fileOptionName]['tmp_name'];
                move_uploaded_file($tempName, $targetLocation . $fileName);
                $gatewaySettings["iconFileUrl"] = trailingslashit(
                                wp_upload_dir()['baseurl']
                        ) . 'mollie-uploads/'. $this->id .'/'. $fileName;
                $gatewaySettings["iconFilePath"] = trailingslashit(
                                wp_upload_dir()['basedir']
                        ) . 'mollie-uploads/'. $this->id .'/'. $fileName;
                update_option("{$this->id}_settings", $gatewaySettings);
            } else {
                $notice = new Mollie_WC_Notice_AdminNotice();
                $message = sprintf(
                        esc_html__(
                                '%1$sMollie Payments for WooCommerce%2$s Unable to upload the file. Size must be under 500kb.',
                                'mollie-payments-for-woocommerce'
                        ),
                        '<strong>',
                        '</strong>'
                );
                $notice->addNotice('notice-error is-dismissible', $message);
            }
        }
    }

    protected function processAdminOptionSurcharge()
    {
        $paymentSurcharge = $this->id . '_payment_surcharge';

        if (isset($_POST[$paymentSurcharge])
                && $_POST[$paymentSurcharge]
                !== Mollie_WC_Helper_GatewaySurchargeHandler::NO_FEE
        ) {
            $surchargeFields = [
                    '_fixed_fee',
                    '_percentage',
                    '_surcharge_limit'
            ];
            foreach ($surchargeFields as $field) {
                $optionName = $this->id . $field;
                $validatedValue = isset($_POST[$optionName])
                        && $_POST[$optionName] > 0
                        && $_POST[$optionName] < 999;
                if (!$validatedValue) {
                    unset($_POST[$optionName]);
                }
            }
        }
    }

    /**
     * @param $url
     * @return string
     */
    protected function asciiDomainName($url)
    {
        if (function_exists('idn_to_ascii')) {
            $parsed = parse_url($url);
            $query = $parsed['query'];
            $url = str_replace('?' . $query, '', $url);
            if (defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46')) {
                $url = idn_to_ascii($url, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) ? idn_to_ascii(
                        $url,
                        IDNA_NONTRANSITIONAL_TO_ASCII,
                        INTL_IDNA_VARIANT_UTS46
                ) : $url;
            } else {
                $url = idn_to_ascii($url) ? idn_to_ascii($url) : $url;
            }
            $url = $url . '?' . $query;
        }

        return $url;
    }
}
