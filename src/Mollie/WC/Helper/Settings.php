<?php

use Mollie\Api\Exceptions\ApiException;

class Mollie_WC_Helper_Settings
{
    const FILTER_ALLOWED_LANGUAGE_CODE_SETTING = 'mollie.allowed_language_code_setting';
    const FILTER_WPML_CURRENT_LOCALE = 'wpml_current_language';

    const DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK = '3:00';

    const SETTING_NAME_PAYMENT_LOCALE = 'payment_locale';
    const SETTING_LOCALE_DEFAULT_LANGUAGE = 'en_US';
    const SETTING_LOCALE_DETECT_BY_BROWSER = 'detect_by_browser';
    const SETTING_LOCALE_WP_LANGUAGE = 'wp_locale';

    const ALLOWED_LANGUAGE_CODES = [
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
        'lt_LT',
    ];

    public function gatewayFormFields(
        $defaultTitle,
        $defaultDescription,
        $paymentConfirmation
    ) {
        $formFields = [
            'enabled' => [
                'title' => __(
                    'Enable/Disable',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => sprintf(
                    __('Enable %s', 'mollie-payments-for-woocommerce'),
                    $defaultTitle
                ),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Title', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(
                    __(
                        'This controls the title which the user sees during checkout. Default <code>%s</code>',
                        'mollie-payments-for-woocommerce'
                    ),
                    $defaultTitle
                ),
                'default' => $defaultTitle,
                'desc_tip' => true,
            ],
            'display_logo' => [
                'title' => __(
                    'Display logo',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => __(
                    'Display logo on checkout page. Default <code>enabled</code>',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'yes'
            ],
            'enable_custom_logo' => [
                'title' => __(
                    'Enable custom logo',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => __(
                    'Enable the feature to add a custom logo for this gateway. This feature will have precedence over other logo options.',
                    'mollie-payments-for-woocommerce'
                )
            ],
            'upload_logo' => [
                'title' => __(
                    'Upload custom logo',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'file',
                'custom_attributes'=>['accept'=>'.png, .jpeg, .svg, image/png, image/jpeg'],
                'description' => sprintf(
                    __(
                        'Upload a custom icon for this gateway. The feature must be enabled.',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'mollie-payments-for-woocommerce'),
                'type' => 'textarea',
                'description' => sprintf(
                    __(
                        'Payment method description that the customer will see on your checkout. Default <code>%s</code>',
                        'mollie-payments-for-woocommerce'
                    ),
                    $defaultDescription
                ),
                'default' => $defaultDescription,
                'desc_tip' => true,
            ],
            'allowed_countries' => [
                'title' => __(
                    'Sell to specific countries',
                    'mollie-payments-for-woocommerce'
                ),
                'desc' => '',
                'css' => 'min-width: 350px;',
                'default' => [],
                'type' => 'multi_select_countries',
            ],
            'payment_surcharge' => [
                'title' => __(
                    'Payment Surcharge',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'select',
                'options' => [
                    Mollie_WC_Helper_GatewaySurchargeHandler::NO_FEE => __(
                        'No fee',
                        'mollie-payments-for-woocommerce'
                    ),
                    Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_FEE => __(
                        'Fixed fee',
                        'mollie-payments-for-woocommerce'
                    ),
                    Mollie_WC_Helper_GatewaySurchargeHandler::PERCENTAGE => __(
                        'Percentage',
                        'mollie-payments-for-woocommerce'
                    ),
                    Mollie_WC_Helper_GatewaySurchargeHandler::FIXED_AND_PERCENTAGE => __(
                        'Fixed fee and percentage',
                        'mollie-payments-for-woocommerce'
                    ),
                ],
                'default' => 'no_fee',
                'description' => __(
                    'Choose a payment surcharge for this gateway',
                    'mollie-payments-for-woocommerce'
                ),
                'desc_tip' => true,
            ],
            'fixed_fee' => [
                'title' => sprintf(__('Payment surcharge fixed amount in %s', 'mollie-payments-for-woocommerce'), html_entity_decode( get_woocommerce_currency_symbol() )),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Control the fee added on checkout. Default 0.01',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes'=>['step'=>'0.01', 'min'=>'0.01', 'max'=>'999'],
                'default' => '0.01',
                'desc_tip' => true,
            ],
            'percentage' => [
                'title' => __('Payment surcharge percentage amount %', 'mollie-payments-for-woocommerce'),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Control the percentage fee added on checkout. Default 0.01',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes'=>['step'=>'0.01', 'min'=>'0.01', 'max'=>'999'],
                'default' => '0.01',
                'desc_tip' => true,
            ],
            'surcharge_limit' => [
                'title' => sprintf(__('Payment surcharge limit in %s', 'mollie-payments-for-woocommerce'), html_entity_decode( get_woocommerce_currency_symbol())),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Limit the maximum fee added on checkout. Default 0',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes'=>['step'=>'0.01', 'min'=>'0.01', 'max'=>'999'],
                'default' => '0.01',
                'desc_tip' => true,
            ],
        ];

        if ($paymentConfirmation) {
            $formFields['initial_order_status'] = [
                'title' => __(
                    'Initial order status',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'select',
                'options' => [
                    Mollie_WC_Gateway_Abstract::STATUS_ON_HOLD => wc_get_order_status_name(
                            Mollie_WC_Gateway_Abstract::STATUS_ON_HOLD
                        ) . ' (' . __(
                            'default',
                            'mollie-payments-for-woocommerce'
                        ) . ')',
                    Mollie_WC_Gateway_Abstract::STATUS_PENDING => wc_get_order_status_name(
                        Mollie_WC_Gateway_Abstract::STATUS_PENDING
                    ),
                ],
                'default' => Mollie_WC_Gateway_Abstract::STATUS_ON_HOLD,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => sprintf(
                    __(
                        'Some payment methods take longer than a few hours to complete. The initial order state is then set to \'%s\'. This ensures the order is not cancelled when the setting %s is used.',
                        'mollie-payments-for-woocommerce'
                    ),
                    wc_get_order_status_name(
                        Mollie_WC_Gateway_Abstract::STATUS_ON_HOLD
                    ),
                    '<a href="' . admin_url(
                        'admin.php?page=wc-settings&tab=products&section=inventory'
                    ) . '" target="_blank">' . __(
                        'Hold Stock (minutes)',
                        'woocommerce'
                    ) . '</a>'
                ),
            ];
        }


        return $formFields;
    }

    /**
     * @return bool
     */
    public function isTestModeEnabled()
    {
        return trim(get_option($this->getSettingId('test_mode_enabled'))) === 'yes';
    }

    /**
     * @param bool $test_mode
     * @return null|string
     */
    public function getApiKey($test_mode = false)
    {
        $setting_id = $test_mode ? 'test_api_key' : 'live_api_key';

        $apiKeyId = $this->getSettingId($setting_id);
        $apiKey = get_option($apiKeyId);

        if (!$apiKey && is_admin()) {
            $apiKey = filter_input(INPUT_POST, $apiKeyId, FILTER_SANITIZE_STRING);
        }

        return trim($apiKey);
    }

    /**
     * Order status for cancelled payments
     *
     * @return string|null
     */
    public function getOrderStatusCancelledPayments()
    {
        return trim(get_option($this->getSettingId('order_status_cancelled_payments')));
    }

    /**
     * Retrieve the Payment Locale Setting from Database
     *
     * @return string
     */
    protected function getPaymentLocaleSetting()
    {
        $option = (string)get_option(
            $this->getSettingId(self::SETTING_NAME_PAYMENT_LOCALE),
            self::SETTING_LOCALE_WP_LANGUAGE
        );

        $option = $option ?: self::SETTING_LOCALE_WP_LANGUAGE;

        return trim($option);
    }

    /**
     * Retrieve the Payment Locale
     *
     * @return string
     */
    public function getPaymentLocale()
    {
        $setting = $this->getPaymentLocaleSetting();

        if ($setting === self::SETTING_LOCALE_DETECT_BY_BROWSER) {
            return $this->browserLanguage();
        }

        $setting === self::SETTING_LOCALE_WP_LANGUAGE
            ? $languageCode = $this->getCurrentLocale()
            : $languageCode = $setting;

        // TODO Missing Post condition, $languageCode has to be check for a valid
        //      language code.

        return $languageCode ?: self::SETTING_LOCALE_DEFAULT_LANGUAGE;
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
        return admin_url('admin.php?page=wc-settings&tab=mollie_settings#' . Mollie_WC_Plugin::PLUGIN_ID);
    }

    /**
     * @return string
     */
    public function getLogsUrl()
    {
        return admin_url('admin.php?page=wc-status&tab=logs');
    }


    /**
     * Update the profileId option on update keys or on changing live/test mode
     *
     * @param $optionValue
     * @param $optionName
     *
     * @return mixed
     */
    public function updateMerchantIdOnApiKeyChanges($optionValue, $optionName)
    {
        $optionId = isset($optionName['id']) ? $optionName['id'] : '';
        $allowedOptionsId = [
            $this->getSettingId('live_api_key'),
            $this->getSettingId('test_api_key'),
        ];

        if (!in_array($optionId, $allowedOptionsId, true)) {
            return $optionValue;
        }

        $merchantProfileIdOptionKey = Mollie_WC_Plugin::PLUGIN_ID . '_profile_merchant_id';

        try {
            $merchantProfile = mollieWooCommerceMerchantProfile();
            $merchantProfileId = isset($merchantProfile->id) ? $merchantProfile->id : '';
        } catch (ApiException $exception) {
            $merchantProfileId = '';
        }

        update_option($merchantProfileIdOptionKey, $merchantProfileId);

        return $optionValue;
    }

    /**
     * Called after the api keys are updated so we can update the profile Id
     *
     * @param $oldValue
     * @param $value
     * @param $optionName
     */
    public function updateMerchantIdAfterApiKeyChanges($oldValue, $value, $optionName)
    {
        $option = ['id'=>$optionName];
        $this->updateMerchantIdOnApiKeyChanges($value, $option);
    }

    /**
     * Get plugin status
     *
     * - Check compatibility
     * - Check Mollie API connectivity
     *
     * @return string
     */
    protected function getPluginStatus()
    {
        $status = Mollie_WC_Plugin::getStatusHelper();

        if (!$status->isCompatible()) {
            // Just stop here!
            return ''
                . '<div class="notice notice-error">'
                . '<p><strong>' . __(
                    'Error',
                    'mollie-payments-for-woocommerce'
                ) . ':</strong> ' . implode('<br/>', $status->getErrors())
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
        $content .= '<div style="width:1000px;height:350px; background:white; padding:10px; margin-top:10px;">';

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

		$content .= '<ul style="width: 1000px; padding:20px 0 0 10px">';

		foreach ( Mollie_WC_Plugin::$GATEWAYS as $gateway_classname ) {
			$gateway = new $gateway_classname;

			// Remove MisterCash from list as it's renamed Bancontact
			if ( $gateway->id == 'mollie_wc_gateway_mistercash' ) {
				continue;
			}

			if ( $gateway instanceof Mollie_WC_Gateway_Abstract ) {
				$content .= '<li style="float: left; width: 32%; height:32px;">';
                $content .= $gateway->getIconUrl();
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

		$content .= '</ul></div>';
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

        return $content;
	}

    /**
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields (array $settings)
    {
        wp_register_script('mollie_wc_admin_settings', Mollie_WC_Plugin::getPluginUrl('/public/js/settings.min.js'), array('jquery'), Mollie_WC_Plugin::PLUGIN_VERSION);
        wp_enqueue_script('mollie_wc_admin_settings');

        $presentationText = __('Quickly integrate all major payment methods in WooCommerce, wherever you need them.', 'mollie-payments-for-woocommerce' );
        $presentationText .= __(' Simply drop them ready-made into your WooCommerce webshop with this powerful plugin by Mollie.', 'mollie-payments-for-woocommerce');
        $presentationText .= __(' Mollie is dedicated to making payments better for WooCommerce. ', 'mollie-payments-for-woocommerce');
        $presentationText .='<p>Please go to <a href="https://www.mollie.com/dashboard/signup" >the signup page </a>';
        $presentationText .= __('to create a new Mollie account and start receiving payments in a couple of minutes. ', 'mollie-payments-for-woocommerce');
        $presentationText .= 'Contact <a href="mailto:info@mollie.com">info@mollie.com</a>';
        $presentationText .= ' if you have any questions or comments about this plugin.</p>';
        $presentationText .= '<p style="border-left: 4px solid black; padding: 8px; height:32px; font-weight:bold; font-size: medium;">Our pricing is always per transaction. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.</p>';


        $presentation = ''
            . '<div style="width:1000px"><div style="font-weight:bold;"><a href="https://github.com/mollie/WooCommerce/wiki">'.__('Plugin Documentation', 'mollie-payments-for-woocommerce').'</a> | <a href="https://mollie.inpsyde.com/docs/how-to-request-support-via-website-widget/">'.__('Contact Support', 'mollie-payments-for-woocommerce').'</a></div></div>'
            . '<span></span>'
            . '<div id="" class="" style="width: 1000px; padding:5px 0 0 10px"><p>'.$presentationText.'</p></div>';

        $content = ''
            . $presentation
            . $this->getPluginStatus()
            . $this->getMollieMethods();

        $debug_desc = __('Log plugin events.', 'mollie-payments-for-woocommerce');

        // Display location of log files

        /* translators: Placeholder 1: Location of the log files */
        $debug_desc .= ' ' . sprintf(
                __(
                    'Log files are saved to <code>%s</code>',
                    'mollie-payments-for-woocommerce'
                ),
                defined('WC_LOG_DIR') ? WC_LOG_DIR
                    : WC()->plugin_path() . '/logs/'
            );


        // Global Mollie settings
        $mollie_settings = array(
            array(
                'id'    => $this->getSettingId('title'),
                'title' => __('Mollie Settings', 'mollie-payments-for-woocommerce'),
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
            [
                'id'      => $this->getSettingId('debug'),
                'title'   => __('Debug Log', 'mollie-payments-for-woocommerce'),
                'type'    => 'checkbox',
                'desc'    => $debug_desc,
                'default' => 'yes',
            ],
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
        $woocommerce_klarnapaylater_gateway = new Mollie_WC_Gateway_KlarnaPayLater();
        $woocommerce_klarnasliceit_gateway = new Mollie_WC_Gateway_KlarnaSliceIt();

        if ($woocommerce_klarnapaylater_gateway->is_available() || $woocommerce_klarnasliceit_gateway->is_available()) {
            $content .= '<div class="notice notice-warning is-dismissible"><p>';
            $content .= __(
                'To accept Klarna payments via Mollie, all default WooCommerce checkout fields should be enabled and required. Please ensure that is the case.',
                'mollie-payments-for-woocommerce'
            );
            $content .= '</p></div> ';

            return $content;
        }


        return $content;
	}

    /**
     * Get current locale by WordPress
     *
     * Default to self::SETTING_LOCALE_DEFAULT_LANGUAGE
     *
     * @return string
     */
    protected function getCurrentLocale()
    {
        $locale = apply_filters(self::FILTER_WPML_CURRENT_LOCALE, get_locale());

        // Convert known exceptions
        $locale = $locale === 'nl_NL_formal' ? 'nl_NL' : $locale;
        $locale = $locale === 'de_DE_formal' ? 'de_DE' : $locale;
        $locale = $locale === 'no_NO' ? 'nb_NO' : $locale;

        return $this->extractValidLanguageCode([$locale]);
    }

    /**
     * Retrieve the browser language
     *
     * @return string
     */
    protected function browserLanguage()
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return self::SETTING_LOCALE_DEFAULT_LANGUAGE;
        }

        $httpAcceptedLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($httpAcceptedLanguages as $index => $languageCode) {
            $languageCode = explode(';', $languageCode)[0];
            if (strpos($languageCode, '-') !== false) {
                $languageCode = str_replace('-', '_', $languageCode);
            }

            $httpAcceptedLanguages[$index] = $languageCode;
        }
        $httpAcceptedLanguages = array_filter($httpAcceptedLanguages);

        if (!$httpAcceptedLanguages) {
            return self::SETTING_LOCALE_DEFAULT_LANGUAGE;
        }

        return $this->extractValidLanguageCode($httpAcceptedLanguages);
    }

    /**
     * Extract a valid code Language from the given arguments
     *
     * The language Code could contains valid language codes that are not supported such as
     * country codes.
     *
     * Since the Browser can send both country and region codes we need to map the country code
     * to a region code on the fly.
     *
     * The method does that, it try to retrieve the language code if it's exists within the
     * allowed language codes dictionary, if not it will try to retrieve the first one that
     * contains the country code.
     *
     * @param array $languageCodes
     * @return string
     */
    protected function extractValidLanguageCode(array $languageCodes)
    {
        // TODO Need Assertion to ensure $languageCodes is not empty and contains only strings

        /**
         * Filter Allowed Language Codes
         *
         * @param array $allowedLanguageCodes
         */
        $allowedLanguageCodes = apply_filters(
            self::FILTER_ALLOWED_LANGUAGE_CODE_SETTING,
            self::ALLOWED_LANGUAGE_CODES
        );

        if (empty($allowedLanguageCodes)) {
            // TODO Need validation for Language Code
            return (string)$languageCodes[0];
        }

        foreach ($languageCodes as $index => $languageCode) {
            if (in_array($languageCode, $allowedLanguageCodes, true)) {
                return $languageCode;
            }
        }

        foreach ($languageCodes as $languageCode) {
            foreach ($allowedLanguageCodes as $currentAllowedLanguageCode) {
                $countryCode = substr($currentAllowedLanguageCode, 0, 2);
                if ($countryCode === $languageCode) {
                    return $currentAllowedLanguageCode;
                }
            }
        }

        return self::SETTING_LOCALE_DEFAULT_LANGUAGE;
    }

    /**
     * Init all the gateways and add to the db for the first time
     * @param $gateway
     */
    protected function updateGatewaySettings($gateway)
    {
        $gateway->settings['enabled'] = $gateway->is_available() ? 'yes' : 'no';
        update_option(
            $gateway->id . "_settings",
            $gateway->settings
        );
    }
}
