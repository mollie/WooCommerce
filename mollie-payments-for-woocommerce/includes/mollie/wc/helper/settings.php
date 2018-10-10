<?php
class Mollie_WC_Helper_Settings
{
    const DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK = '3:00';
    /**
     * @return bool
     */
    public function isTestModeEnabled ()
    {
        return trim(get_option($this->getSettingId('test_mode_enabled'))) === 'yes';
    }

    /**
     * @param bool $test_mode
     * @return null|string
     */
    public function getApiKey ($test_mode = false)
    {
        $setting_id = $test_mode ? 'test_api_key' : 'live_api_key';

        return trim(get_option($this->getSettingId($setting_id)));
    }

    /**
     * Description send to Mollie
     *
     * @return string|null
     */
    public function getPaymentDescription ()
    {
        return trim(get_option($this->getSettingId('payment_description')));
    }

	/**
	 * Order status for cancelled payments
	 *
	 * @return string|null
	 */
	public function getOrderStatusCancelledPayments ()
	{
		return trim(get_option($this->getSettingId('order_status_cancelled_payments')));
	}

    /**
     * @return string
     */
    protected function getPaymentLocaleSetting ()
    {
        $default_value = 'wp_locale';

        return trim(get_option($this->getSettingId('payment_locale'), $default_value));
    }

    /**
     * @return string|null
     */
    public function getPaymentLocale ()
    {
        $setting = $this->getPaymentLocaleSetting();

        if (!empty($setting))
        {
            if ($setting == 'wp_locale')
            {
                // Send current locale to Mollie
                return $this->getCurrentLocale();
            }
            else
            {
                // Send specific locale to Mollie
                return $setting;
            }
        }

        // If setting is empty, users selected "Use browser language"
	    // Locale is now required for Orders API, so that's useless
	    // Fall back to en_US if we have no other options
        return 'en_US';
    }

    /**
     * Get current locale
     *
     * @return string
     */
	public function getCurrentLocale() {
		$locale = apply_filters( 'wpml_current_language', get_locale() );

		// Convert known exceptions
		( $locale == 'nl_NL_formal' ? $locale = 'nl_NL': '');
		( $locale == 'no_NO' ? $locale = 'nb_NO': '');

		// TODO: Once in a while, check API changelog to make sure there haven't been changes to this list.
		$valid_locales = array (
			'en_US',
			'nl_NL',
			'nl_BE',
			'fr_FR',
			'fr_BE',
			'de_DE',
			'de_AT',
			'de_CH',
			'es_ES',
			'ca_ES',
			'pt_PT',
			'it_IT',
			'nb_NO',
			'sv_SE',
			'fi_FI',
			'da_DK',
			'is_IS',
			'hu_HU',
			'pl_PL',
			'lv_LV',
			'lt_LT'
		);

		// Check if the current WordPress locale is valid for Mollie Orders API
		if ( in_array( $locale, $valid_locales ) ) {
			return $locale;
		}

		// If current WordPress locale is invalid, try to get the correct format
		// by searching for part of a needle in a haystack
		foreach ($valid_locales as $key => $value) {
			if (false !== stripos($value, $locale)) {
				return $value;
			}
		}

		// Fall back to en_US if we have no other options
		return 'en_US';
	}

    /**
     * Store customer details at Mollie
     *
     * @return string
     */
    public function shouldStoreCustomer ()
    {
        return get_option($this->getSettingId('customer_details'), 'yes') === 'yes';
    }

    /**
     * @return bool
     */
    public function isDebugEnabled ()
    {
        return get_option($this->getSettingId('debug'), 'yes') === 'yes';
    }

    /**
     * @return string
     */
    public function getGlobalSettingsUrl ()
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout#' . Mollie_WC_Plugin::PLUGIN_ID);
    }

    /**
     * @return string
     */
    public function getLogsUrl ()
    {
        return admin_url('admin.php?page=wc-status&tab=logs');
    }

    /**
     * Get plugin status
     *
     * - Check compatibility
     * - Check Mollie API connectivity
     *
     * @return string
     */
    protected function getPluginStatus ()
    {
        $status = Mollie_WC_Plugin::getStatusHelper();

        if (!$status->isCompatible())
        {
            // Just stop here!
            return ''
                . '<div class="notice notice-error">'
                . '<p><strong>' . __('Error', 'mollie-payments-for-woocommerce') . ':</strong> ' . implode('<br/>', $status->getErrors())
                . '</p></div>';
        }

        try
        {
            // Check compatibility
            $status->getMollieApiStatus();

            $api_status       = ''
                . '<p>' . __('Mollie status:', 'mollie-payments-for-woocommerce')
                . ' <span style="color:green; font-weight:bold;">' . __('Connected', 'mollie-payments-for-woocommerce') . '</span>'
                . '</p>';
            $api_status_type = 'updated';
        }
        catch (\Mollie\Api\Exceptions\ApiException $e)
        {

            $api_status = ''
                . '<p style="font-weight:bold;"><span style="color:red;">Communicating with Mollie failed:</span> ' . $e->getMessage() . '</p>'
                . '<p>Please view the FAQ item <a href="https://github.com/mollie/WooCommerce/wiki/Common-issues#communicating-with-mollie-failed" target="_blank">Communicating with Mollie failed</a> if this does not fix your problem.';

            $api_status_type = 'error';
        }

        return ''
            . '<div id="message" class="' . $api_status_type . ' fade notice">'
            . $api_status
            . '</div>';
    }

    /**
     * @param string $gateway_class_name
     * @return string
     */
    protected function getGatewaySettingsUrl ($gateway_class_name)
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($gateway_class_name)));
    }

	protected function getMollieMethods() {
		$content = '';

		$data_helper     = Mollie_WC_Plugin::getDataHelper();
		$settings_helper = Mollie_WC_Plugin::getSettingsHelper();

		// Is Test mode enabled?
		$test_mode = $settings_helper->isTestModeEnabled();

		if ( isset( $_GET['refresh-methods'] ) && wp_verify_nonce( $_GET['nonce_mollie_refresh_methods'], 'nonce_mollie_refresh_methods' ) ) {
			/* Reload active Mollie methods */
			$data_helper->getAllPaymentMethods( $test_mode, $use_cache = false );
		}

		$icon_available    = ' <span style="color: green; cursor: help;" title="' . __( 'Gateway enabled', 'mollie-payments-for-woocommerce' ) . '">' . strtolower( __( 'Enabled', 'mollie-payments-for-woocommerce' ) ) . '</span>';
		$icon_no_available = ' <span style="color: red; cursor: help;" title="' . __( 'Gateway disabled', 'mollie-payments-for-woocommerce' ) . '">' . strtolower( __( 'Disabled', 'mollie-payments-for-woocommerce' ) ) . '</span>';

		$content .= '<br /><br />';

		if ( $test_mode ) {
			$content .= '<strong>' . __( 'Test mode enabled.', 'mollie-payments-for-woocommerce' ) . '</strong> ';
		}

		$content .= sprintf(
		/* translators: The surrounding %s's Will be replaced by a link to the Mollie profile */
			__( 'The following payment methods are activated in your %sMollie profile%s:', 'mollie-payments-for-woocommerce' ),
			'<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
			'</a>'
		);

		// Set a "refresh" link so payment method status can be refreshed from Mollie API
		$nonce_mollie_refresh_methods = wp_create_nonce( 'nonce_mollie_refresh_methods' );
		$refresh_methods_url = add_query_arg( array ( 'refresh-methods' => 1, 'nonce_mollie_refresh_methods' => $nonce_mollie_refresh_methods ) );

		$content .= ' (<a href="' . esc_attr( $refresh_methods_url ) . '">' . strtolower( __( 'Refresh', 'mollie-payments-for-woocommerce' ) ) . '</a>)';

		$content .= '<ul style="width: 1000px">';

		foreach ( Mollie_WC_Plugin::$GATEWAYS as $gateway_classname ) {
			$gateway = new $gateway_classname;

			// Remove MisterCash from list as it's renamed Bancontact
			if ( $gateway->id == 'mollie_wc_gateway_mistercash' ) {
				continue;
			}

			// Remove Klarna from list if not at least WooCommerce 3.x is used
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				if ( $gateway->id == 'mollie_wc_gateway_klarnapaylater' || $gateway->id == 'mollie_wc_gateway_klarnasliceit' ) {
					continue;
				}
			}

			if ( $gateway instanceof Mollie_WC_Gateway_Abstract ) {
				$content .= '<li style="float: left; width: 33%;">';

				$content .= '<img src="' . esc_attr( $gateway->getIconUrl() ) . '" alt="' . esc_attr( $gateway->getDefaultTitle() ) . '" title="' . esc_attr( $gateway->getDefaultTitle() ) . '" style="width: 25px; vertical-align: bottom;" />';
				$content .= ' ' . esc_html( $gateway->getDefaultTitle() );

				if ( $gateway->is_available() ) {
					$content .= $icon_available;
				} else {
					$content .= $icon_no_available;
				}

				$content .= ' <a href="' . $this->getGatewaySettingsUrl( $gateway_classname ) . '">' . strtolower( __( 'Edit', 'mollie-payments-for-woocommerce' ) ) . '</a>';

				$content .= '</li>';
			}
		}

		$content .= '</ul>';
		$content .= '<div class="clear"></div>';

		// Make sure users also enable iDEAL when they enable SEPA Direct Debit
		// iDEAL is needed for the first payment of subscriptions with SEPA Direct Debit
		$content = $this->checkDirectDebitStatus( $content );

		// Advice users to use bank transfer via Mollie, not
		// WooCommerce default BACS method
		$content = $this->checkMollieBankTransferNotBACS( $content );

		// Warn users that all default WooCommerce checkout fields
		// are required to accept Klarna as payment method
		$content = $this->warnAboutRequiredCheckoutFieldForKlarna( $content );

		// Warn users that at least WooCommerce 3.x is required to accept Klarna as payment method
		$content = $this->warnWoo3xRequiredForKlarna( $content );

		return $content;
	}

    /**
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields (array $settings)
    {
        wp_register_script('mollie_wc_admin_settings', Mollie_WC_Plugin::getPluginUrl('/assets/js/settings.js'), array('jquery'), Mollie_WC_Plugin::PLUGIN_VERSION);
        wp_enqueue_script('mollie_wc_admin_settings');

        $content = ''
            . $this->getPluginStatus()
            . $this->getMollieMethods();

        /* translators: Default payment description. {order_number} and {order_date} are available tags. */
        $default_payment_description = __('Order {order_number}', 'mollie-payments-for-woocommerce');
        $payment_description_tags    = '<code>{order_number}</code>, <code>{order_date}</code>';

        $debug_desc = __('Log plugin events.', 'mollie-payments-for-woocommerce');

        // For WooCommerce 2.2.0+ display view logs link
        if (version_compare(Mollie_WC_Plugin::getStatusHelper()->getWooCommerceVersion(), '2.2.0', ">="))
        {
            $debug_desc .= ' <a href="' . $this->getLogsUrl() . '">' . __('View logs', 'mollie-payments-for-woocommerce') . '</a>';
        }
        // Display location of log files
        else
        {
            /* translators: Placeholder 1: Location of the log files */
            $debug_desc .= ' ' . sprintf(__('Log files are saved to <code>%s</code>', 'mollie-payments-for-woocommerce'), defined('WC_LOG_DIR') ? WC_LOG_DIR : WC()->plugin_path() . '/logs/');
        }

        // Global Mollie settings
        $mollie_settings = array(
            array(
                'id'    => $this->getSettingId('title'),
                'title' => __('Mollie settings', 'mollie-payments-for-woocommerce'),
                'type'  => 'title',
                'desc'  => '<p id="' . Mollie_WC_Plugin::PLUGIN_ID . '">' . $content . '</p>'
                         . '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'mollie-payments-for-woocommerce') . '</p>',
            ),
            array(
                'id'                => $this->getSettingId('live_api_key'),
                'title'             => __('Live API key', 'mollie-payments-for-woocommerce'),
                'default'           => '',
                'type'              => 'text',
                'desc'              => sprintf(
                    /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%s</strong> API key in your %sMollie profile%s', 'mollie-payments-for-woocommerce'),
                    'live',
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                ),
                'css'               => 'width: 350px',
                'placeholder'       => $live_placeholder = __('Live API key should start with live_', 'mollie-payments-for-woocommerce'),
                'custom_attributes' => array(
                    'placeholder' => $live_placeholder,
                    'pattern'     => '^live_\w{30,}$',
                ),
            ),
            array(
                'id'                => $this->getSettingId('test_mode_enabled'),
                'title'             => __('Enable test mode', 'mollie-payments-for-woocommerce'),
                'default'           => 'no',
                'type'              => 'checkbox',
                'desc_tip'          => __('Enable test mode if you want to test the plugin without using real payments.', 'mollie-payments-for-woocommerce'),
            ),
            array(
                'id'                => $this->getSettingId('test_api_key'),
                'title'             => __('Test API key', 'mollie-payments-for-woocommerce'),
                'default'           => '',
                'type'              => 'text',
                'desc'              => sprintf(
                    /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%s</strong> API key in your %sMollie profile%s', 'mollie-payments-for-woocommerce'),
                    'test',
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                ),
                'css'               => 'width: 350px',
                'placeholder'       => $test_placeholder = __('Test API key should start with test_', 'mollie-payments-for-woocommerce'),
                'custom_attributes' => array(
                    'placeholder' => $test_placeholder,
                    'pattern'     => '^test_\w{30,}$',
                ),
            ),
            array(
                'id'      => $this->getSettingId('payment_description'),
                'title'   => __('Description', 'mollie-payments-for-woocommerce'),
                'type'    => 'text',
                /* translators: Placeholder 1: Default payment description, placeholder 2: list of available tags */
                'desc'    => sprintf(__('Payment description send to Mollie. Default <code>%s</code><br/>You can use the following tags: %s', 'mollie-payments-for-woocommerce'), $default_payment_description, $payment_description_tags),
                'default' => $default_payment_description,
                'css'     => 'width: 350px',
            ),
	        array(
		        'id'      => $this->getSettingId('order_status_cancelled_payments'),
		        'title'   => __('Order status after cancelled payment', 'mollie-payments-for-woocommerce'),
		        'type'    => 'select',
		        'options' => array(
			        'pending'          => __('Pending', 'woocommerce'),
			        'cancelled'     => __('Cancelled', 'woocommerce'),
		        ),
		        'desc'    => __('Status for orders when a payment (not a Mollie order via the Orders API) is cancelled. Default: pending. Orders with status Pending can be paid with another payment method, customers can try again. Cancelled orders are final. Set this to Cancelled if you only have one payment method or don\'t want customers to re-try paying with a different payment method. This doesn\'t apply to payments for orders via the new Orders API and Klarna payments.', 'mollie-payments-for-woocommerce'),
		        'default' => 'pending',
	        ),
	        array(
                'id'      => $this->getSettingId('payment_locale'),
                'title'   => __('Payment screen language', 'mollie-payments-for-woocommerce'),
                'type'    => 'select',
                'options' => array(
                    'wp_locale' => __('Automatically send WordPress language', 'mollie-payments-for-woocommerce'),
                    'en_US' => __('English', 'mollie-payments-for-woocommerce'),
                    'nl_NL' => __('Dutch', 'mollie-payments-for-woocommerce'),
                    'nl_BE' => __('Flemish (Belgium)', 'mollie-payments-for-woocommerce'),
                    'fr_FR' => __('French', 'mollie-payments-for-woocommerce'),
                    'fr_BE' => __('French (Belgium)', 'mollie-payments-for-woocommerce'),
                    'de_DE' => __('German', 'mollie-payments-for-woocommerce'),
                    'de_AT' => __('Austrian German', 'mollie-payments-for-woocommerce'),
                    'de_CH' => __('Swiss German', 'mollie-payments-for-woocommerce'),
                    'es_ES' => __('Spanish', 'mollie-payments-for-woocommerce'),
                    'ca_ES' => __('Catalan', 'mollie-payments-for-woocommerce'),
                    'pt_PT' => __('Portuguese', 'mollie-payments-for-woocommerce'),
                    'it_IT' => __('Italian', 'mollie-payments-for-woocommerce'),
                    'nb_NO' => __('Norwegian', 'mollie-payments-for-woocommerce'),
                    'sv_SE' => __('Swedish', 'mollie-payments-for-woocommerce'),
                    'fi_FI' => __('Finnish', 'mollie-payments-for-woocommerce'),
                    'da_DK' => __('Danish', 'mollie-payments-for-woocommerce'),
                    'is_IS' => __('Icelandic', 'mollie-payments-for-woocommerce'),
                    'hu_HU' => __('Hungarian', 'mollie-payments-for-woocommerce'),
                    'pl_PL' => __('Polish', 'mollie-payments-for-woocommerce'),
                    'lv_LV' => __('Latvian', 'mollie-payments-for-woocommerce'),
                    'lt_LT' => __('Lithuanian', 'mollie-payments-for-woocommerce'),
                ),
                'desc'    => sprintf(
                	__('Sending a language (or locale) is required. The option \'Automatically send WordPress language\' will try get the customer\'s language in WordPress (and respects multilanguage plugins) and convert it to a format Mollie understands. If this fails, or if the language is not supported, it will fall back to American English. You can also select one of the locales currently supported by Mollie, that will then be used for all customers.', 'mollie-payments-for-woocommerce'),
	                '<a href="https://www.mollie.com/nl/docs/reference/payments/create" target="_blank">',
	                '</a>'
                ),
                'default' => '',
            ),
            array(
                'id'                => $this->getSettingId('customer_details'),
                'title'             => __('Store customer details at Mollie', 'mollie-payments-for-woocommerce'),
                /* translators: Placeholder 1: enabled or disabled */
                'desc'              => sprintf(__('Should Mollie store customers name and email address for Single Click Payments? Default <code>%s</code>. Required if WooCommerce Subscriptions is being used!', 'mollie-payments-for-woocommerce'), strtolower(__('Enabled', 'mollie-payments-for-woocommerce'))),
                'type'              => 'checkbox',
                'default'           => 'yes',

            ),
            array(
                'id'      => $this->getSettingId('debug'),
                'title'   => __('Debug Log', 'mollie-payments-for-woocommerce'),
                'type'    => 'checkbox',
                'desc'    => $debug_desc,
                'default' => 'yes',
            ),
            array(
                'id'   => $this->getSettingId('sectionend'),
                'type' => 'sectionend',
            ),
        );

        return $this->mergeSettings($settings, $mollie_settings);
    }

    public function getPaymentConfirmationCheckTime()
    {
        $time = strtotime(self::DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK);
        $date = new DateTime();

        if ($date->getTimestamp() > $time){
            $date->setTimestamp($time);
            $date->add(new DateInterval('P1D'));
        } else {
            $date->setTimestamp($time);
        }


        return $date->getTimestamp();
    }

    /**
     * @param string $setting
     * @return string
     */
    protected function getSettingId ($setting)
    {
        global $wp_version;

        $setting_id        = Mollie_WC_Plugin::PLUGIN_ID . '_' . trim($setting);
        $setting_id_length = strlen($setting_id);

        $max_option_name_length = 191;

        /**
         * Prior to WooPress version 4.4.0, the maximum length for wp_options.option_name is 64 characters.
         * @see https://core.trac.wordpress.org/changeset/34030
         */
        if ($wp_version < '4.4.0') {
            $max_option_name_length = 64;
        }

        if ($setting_id_length > $max_option_name_length)
        {
            trigger_error("Setting id $setting_id ($setting_id_length) to long for database column wp_options.option_name which is varchar($max_option_name_length).", E_USER_WARNING);
        }

        return $setting_id;
    }

    /**
     * @param array $settings
     * @param array $mollie_settings
     * @return array
     */
    protected function mergeSettings(array $settings, array $mollie_settings)
    {
        $new_settings           = array();
        $mollie_settings_merged = false;

        // Find payment gateway options index
        foreach ($settings as $index => $setting) {
            if (isset($setting['id']) && $setting['id'] == 'payment_gateways_options'
                && (!isset($setting['type']) || $setting['type'] != 'sectionend')
            ) {
                $new_settings           = array_merge($new_settings, $mollie_settings);
                $mollie_settings_merged = true;
            }

            $new_settings[] = $setting;
        }

        // Mollie settings not merged yet, payment_gateways_options not found
        if (!$mollie_settings_merged)
        {
            // Append Mollie settings
            $new_settings = array_merge($new_settings, $mollie_settings);
        }

        return $new_settings;
    }

	/**
	 * @param $content
	 *
	 * @return string
	 */
	protected function checkDirectDebitStatus( $content ) {

		$ideal_gateway = new Mollie_WC_Gateway_iDEAL();
		$sepa_gateway  = new Mollie_WC_Gateway_DirectDebit();

		if ( ( class_exists( 'WC_Subscription' ) ) && ( $ideal_gateway->is_available() ) && ( ! $sepa_gateway->is_available() ) ) {

			$warning_message = __( 'You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL and/or other "first" payment methods.', 'mollie-payments-for-woocommerce' );

			$content .= '<div class="notice notice-warning is-dismissible"><p>';
			$content .= $warning_message;
			$content .= '</p></div> ';

			$content .= '<strong><p>';
			$content .= $warning_message;
			$content .= '</p></strong> ';

			return $content;
		}

		return $content;
	}

	/**
	 * @param $content
	 *
	 * @return string
	 */
	protected function checkMollieBankTransferNotBACS( $content ) {

		$woocommerce_banktransfer_gateway  = new WC_Gateway_BACS();

		if ( $woocommerce_banktransfer_gateway->is_available() ) {

			$content .= '<div class="notice notice-warning is-dismissible"><p>';
			$content .= __( 'You have the WooCommerce default Direct Bank Transfer (BACS) payment gateway enabled in WooCommerce. Mollie strongly advices only using Bank Transfer via Mollie and disabling the default WooCommerce BACS payment gateway to prevent possible conflicts.', 'mollie-payments-for-woocommerce' );
			$content .= '</p></div> ';

			$content .= '<strong><p>';
			$content .= __( 'You have the WooCommerce default Direct Bank Transfer (BACS) payment gateway enabled in WooCommerce. Mollie strongly advices only using Bank Transfer via Mollie and disabling the default WooCommerce BACS payment gateway to prevent possible conflicts.', 'mollie-payments-for-woocommerce' );
			$content .= '</p></strong> ';

			return $content;
		}

		return $content;
	}

	/**
	 * @param $content
	 *
	 * @return string
	 */
	protected function warnAboutRequiredCheckoutFieldForKlarna( $content ) {

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {

			$woocommerce_klarnapaylater_gateway = new Mollie_WC_Gateway_KlarnaPayLater();
			$woocommerce_klarnasliceit_gateway  = new Mollie_WC_Gateway_KlarnaSliceIt();

			if ( $woocommerce_klarnapaylater_gateway->is_available() || $woocommerce_klarnasliceit_gateway->is_available() ) {

				$content .= '<div class="notice notice-error is-dismissible"><p>';
				$content .= __( 'To accept Klarna payments via Mollie, all default WooCommerce checkout fields should be enabled and required.', 'mollie-payments-for-woocommerce' );
				$content .= '</p></div> ';

				$content .= '<strong><p>';
				$content .= __( 'To accept Klarna payments via Mollie, all default WooCommerce checkout fields should be enabled and required.', 'mollie-payments-for-woocommerce' );
				$content .= '</p></strong> ';

				return $content;
			}
		}

		return $content;
	}

	/**
	 * @param $content
	 *
	 * @return string
	 */
	protected function warnWoo3xRequiredForKlarna( $content ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			$woocommerce_klarnapaylater_gateway = new Mollie_WC_Gateway_KlarnaPayLater();
			$woocommerce_klarnasliceit_gateway  = new Mollie_WC_Gateway_KlarnaSliceIt();

			if ( $woocommerce_klarnapaylater_gateway->is_available() || $woocommerce_klarnasliceit_gateway->is_available() ) {

				$content .= '<div class="notice notice-error is-dismissible"><p>';
				$content .= sprintf(__( 'To accept Klarna payments via Mollie, you need to use at least WooCommerce 3.0 or higher, you are now using version %s.', 'mollie-payments-for-woocommerce' ), WC_VERSION);
				$content .= '</p></div> ';

				$content .= '<strong><p>';
				$content .= sprintf(__( 'To accept Klarna payments via Mollie, you need to use at least WooCommerce 3.0 or higher, you are now using version %s.', 'mollie-payments-for-woocommerce' ), WC_VERSION);
				$content .= '</p></strong> ';

				return $content;
			}
		}

		return $content;
	}

}
