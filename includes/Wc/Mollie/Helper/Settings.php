<?php
class WC_Mollie_Helper_Settings
{
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
     * @return string
     */
    protected function getPaymentLocaleSetting ()
    {
        return trim(get_option($this->getSettingId('payment_locale')));
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
                return get_locale();
            }
            else
            {
                return $setting;
            }
        }

        return null;
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
        return admin_url('admin.php?page=wc-settings&tab=checkout#' . WC_Mollie::PLUGIN_ID);
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
        $status = new WC_Mollie_Helper_Status();

        try
        {
            // First check if the platform is compatible
            $status->checkCompatibility();
        }
        catch (WC_Mollie_Exception_IncompatiblePlatform $e)
        {
            switch ($e->getCode()) {
                case WC_Mollie_Exception_IncompatiblePlatform::API_CLIENT_NOT_INSTALLED:
                    $error = __('Mollie API client not installed. Please make sure the plugin is installed correctly.', 'woocommerce-mollie-payments');
                    break;

                default:
                    $error = esc_html($e->getMessage());
                    break;
            }

            // Just stop here!
            return ''
                . '<div id="message" class="error fade">'
                . ' <strong>' . __('Error', 'woocommerce-mollie-payments') . ':</strong> ' . $error
                . '</div>';
        }

        try
        {
            // Check compatibility
            $status->getMollieApiStatus();

            $api_status       = ''
                . '<p>' . __('Mollie status:', 'woocommerce-mollie-payments')
                . ' <span style="color:green; font-weight:bold;">' . __('Connected', 'woocommerce-mollie-payments') . '</span>'
                . '</p>';
            $api_status_type = 'updated';
        }
        catch (WC_Mollie_Exception_CouldNotConnectToMollie $e)
        {
            $api_status = ''
                . '<p style="font-weight:bold;"><span style="color:red;">Communicating with Mollie failed:</span> ' . esc_html($e->getMessage()) . '</p>'
                . '<p>Please check the following conditions. You can ask your system administrator to help with this.</p>'

                . '<ul style="color: #2D60B0;">'
                . ' <li>Make sure outside connections to <strong>' . esc_html(WC_Mollie_Helper_Api::getApiEndpoint()) . '</strong> are not blocked.</li>'
                . ' <li>Make sure SSL v3 is disabled on your server. Mollie does not support SSL v3.</li>'
                . ' <li>Make sure your server is up-to-date and the latest security patches have been installed.</li>'
                . '</ul><br/>'

                . '<p>Please contact <a href="mailto:info@mollie.com">info@mollie.com</a> if this still does not fix your problem.</p>';

            $api_status_type = 'error';
        }
        catch (WC_Mollie_Exception_InvalidApiKey $e)
        {
            $api_status      = '<p style="color:red; font-weight:bold;">' . esc_html($e->getMessage()) . '</p>';
            $api_status_type = 'error';
        }

        return ''
            . '<div id="message" class="' . $api_status_type . ' fade">'
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

    protected function getMollieMethods ()
    {
        $content = '';

        try
        {
            $data_helper     = WC_Mollie::getDataHelper();
            $settings_helper = WC_Mollie::getSettingsHelper();

            // Is Test mode enabled?
            $test_mode       = $settings_helper->isTestModeEnabled();

            $mollie_methods  = $data_helper->getPaymentMethods($test_mode);
            $mollie_gateways = array();

            // Find all payment methods supported by plugin' gateways
            foreach (WC_Mollie::$GATEWAYS as $gateway_classname)
            {
                $gateway = new $gateway_classname;

                if ($gateway instanceof WC_Mollie_Gateway_Abstract)
                {
                    $mollie_gateways[$gateway->getMollieMethodId()] = $gateway;
                }
            }

            $icon_available     = ' <span style="color: green; cursor: help;" title="' . __('Gateway enabled', 'woocommerce-mollie-payments'). '">' . strtolower(__('Enabled', 'woocommerce-mollie-payments')) . '</span>';
            $icon_no_available  = ' <span style="color: orange; cursor: help;" title="' . __('Gateway disabled', 'woocommerce-mollie-payments'). '">' . strtolower(__('Disabled', 'woocommerce-mollie-payments')) . '</span>';
            $icon_not_supported = ' <span style="color: red; cursor: help;" title="' . __('This payment method is not supported by this plugin. Please check if there is an update available.', 'woocommerce-mollie-payments'). '">' . strtolower(__('Not supported', 'woocommerce-mollie-payments')) . '</span>';

            $content .= '<br /><br />';

            if (WC_Mollie::getSettingsHelper()->isTestModeEnabled())
            {
                $content .= '<strong>' . __('Test mode enabled.', 'woocommerce-mollie-payments') . '</strong> ';
            }

            $content .= sprintf(
                /* translators: The surrounding %s's Will be replaced by a link to the Mollie profile */
                __('The following payment methods are activated in your %sMollie profile%s:', 'woocommerce-mollie-payments'),
                '<a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">',
                '</a>'
            );

            $content .= '<ul style="width: 1000px">';
            foreach ($mollie_methods as $payment_method)
            {
                $content .= '<li style="float: left; width: 33%;">';
                $content .= '<img src="' . esc_attr($payment_method->image->normal) . '" alt="' . esc_attr($payment_method->description) . '" title="' . esc_attr($payment_method->description) . '" style="width: 25px; vertical-align: bottom;" />';
                $content .= ' ' . esc_html($payment_method->description);

                // Gateway found for Mollie payment method
                if (isset($mollie_gateways[$payment_method->id]))
                {
                    /** @var WC_Mollie_Gateway_Abstract $gateway */
                    $gateway = $mollie_gateways[$payment_method->id];

                    if ($gateway->is_available())
                    {
                        $content .= $icon_available;
                    }
                    else
                    {
                        $content .= $icon_no_available;
                    }

                    $content .= ' <a href="' . $this->getGatewaySettingsUrl(get_class($gateway)) . '">' . strtolower(__('Edit', 'woocommerce-mollie-payments')) . '</a>';
                }
                // No plugin gateway found with support for this payment method
                else
                {
                    $content .= $icon_not_supported;
                }

                $content .= '</li>';

            }
            $content .= '</ul>';
            $content .= '<div class="clear"></div>';
        }
        catch (WC_Mollie_Exception_InvalidApiKey $e)
        {
            // Ignore
        }

        return $content;
    }

    /**
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields (array $settings)
    {
        wp_register_script('mollie_settings', plugin_dir_url('woocommerce-mollie-payments/woocommerce-mollie-payments.php')  . '/assets/js/settings.js', array('jquery'), WC_Mollie::PLUGIN_VERSION);
        wp_enqueue_script('mollie_settings');

        $content = ''
            . $this->getPluginStatus()
            . $this->getMollieMethods();

        /* translators: Default payment description. '%' is a placeholder the user can use and will be replaced by the order number */
        $default_payment_description = __('Order %', 'woocommerce-mollie-payments');

        // Global Mollie settings
        $mollie_settings = array(
            array(
                'id'    => $this->getSettingId('title'),
                'title' => __('Mollie settings', 'woocommerce-mollie-payments'),
                'type'  => 'title',
                'desc'  => '<p id="' . WC_Mollie::PLUGIN_ID . '">' . $content . '</p>'
                         . '<p>' . __('The following options are required to use the Mollie payments and are used by all Mollie payment methods', 'woocommerce-mollie-payments') . '</p>',
            ),
            array(
                'id'                => $this->getSettingId('live_api_key'),
                'title'             => __('Live API key', 'woocommerce-mollie-payments'),
                'type'              => 'text',
                'desc'              => sprintf(
                    /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%s</strong> API key in your %sMollie profile%s', 'woocommerce-mollie-payments'),
                    'live',
                    '<a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">',
                    '</a>'
                ),
                'placeholder'       => __('Live API key should start with live_', 'woocommerce-mollie-payments'),
                'css'               => 'width: 350px',
                'custom_attributes' => array(
                    'pattern' => '^live_\w+$',
                ),
            ),
            array(
                'id'                => $this->getSettingId('test_mode_enabled'),
                'title'             => __('Enable test mode', 'woocommerce-mollie-payments'),
                'default'           => 'no',
                'type'              => 'checkbox',
                'desc_tip'          => __('Enable test mode if you want to test the plugin without using real payments.', 'woocommerce-mollie-payments'),
            ),
            array(
                'id'                => $this->getSettingId('test_api_key'),
                'title'             => __('Test API key', 'woocommerce-mollie-payments'),
                'placeholder'       => __('Test API key should start with test_', 'woocommerce-mollie-payments'),
                'default'           => '',
                'type'              => 'text',
                'css'               => 'width: 350px',
                'desc'              => sprintf(
                    /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%s</strong> API key in your %sMollie profile%s', 'woocommerce-mollie-payments'),
                    'test',
                    '<a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">',
                    '</a>'
                ),
                'custom_attributes' => array(
                    'pattern' => '^test_\w+$',
                ),
            ),
            array(
                'id'      => $this->getSettingId('payment_description'),
                'title'   => __('Description', 'woocommerce-mollie-payments'),
                'type'    => 'text',
                'desc'    => sprintf(__('Payment description send to Mollie. <code>%%</code> will be replaced by the order number. Default <code>%s</code>', 'woocommerce-mollie-payments'), $default_payment_description),
                'default' => $default_payment_description,
                'css'     => 'width: 350px',
            ),
            array(
                'id'      => $this->getSettingId('payment_locale'),
                'title'   => __('Payment screen language', 'woocommerce-mollie-payments'),
                'type'    => 'select',
                'options' => array(
                    ''          => __('Detect using browser language', 'woocommerce-mollie-payments') . ' (' . __('default', 'woocommerce-mollie-payments') . ')',
                    /* translators: Placeholder 1: Current WordPress locale */
                    'wp_locale' => sprintf(__('Send WordPress language (%s)', 'woocommerce-mollie-payments'), get_locale()),
                    'nl_NL'     => __('Dutch', 'woocommerce-mollie-payments'),
                    'nl_BE'     => __('Flemish (Belgium)', 'woocommerce-mollie-payments'),
                    'en'        => __('English', 'woocommerce-mollie-payments'),
                    'de'        => __('German', 'woocommerce-mollie-payments'),
                    'es'        => __('Spanish', 'woocommerce-mollie-payments'),
                    'fr_FR'     => __('French', 'woocommerce-mollie-payments'),
                    'fr_BE'     => __('French (Belgium)', 'woocommerce-mollie-payments'),
                ),
                'default' => '',
            ),
            array(
                'id'      => $this->getSettingId('debug'),
                'title'   => __('Debug Log', 'woocommerce-mollie-payments'),
                'type'    => 'checkbox',
                /* translators: Placeholder 1: A link to the debug logs */
                'desc'    => sprintf(__('Log plugin events. <a href="%s">View logs</a>', 'woocommerce-mollie-payments'), $this->getLogsUrl()),
                'default' => 'yes',
            ),
            array(
                'id'   => $this->getSettingId('sectionend'),
                'type' => 'sectionend',
            ),
        );

        return $this->mergeSettings($settings, $mollie_settings);
    }

    /**
     * Called when page 'WooCommerce -> Checkout -> Checkout Options' is saved
     */
    public function onGlobalSettingsSaved ()
    {
        WC_Mollie::debug(__METHOD__ . ': Mollie settings saved, delete transients');

        delete_transient(WC_Mollie::PLUGIN_ID . '_api_methods');
        delete_transient(WC_Mollie::PLUGIN_ID . '_api_issuers');
    }

    /**
     * @param string $setting
     * @return string
     */
    protected function getSettingId ($setting)
    {
        return WC_Mollie::PLUGIN_ID . '_' . trim($setting);
    }

    /**
     * @param array $settings
     * @param array $mollie_settings
     * @return array
     */
    protected function mergeSettings(array $settings, array $mollie_settings)
    {
        $insert_after_index = NULL;

        // Find payment gateway options index
        foreach ($settings as $index => $setting) {
            if (isset($setting['id']) && $setting['id'] == 'payment_gateways_options'
                && (!isset($setting['type']) || $setting['type'] != 'sectionend')
            ) {
                $insert_after_index = $index;
                break;
            }
        }

        // Payment gateways setting found
        if ($insert_after_index !== NULL)
        {
            // Insert Mollie settings before payment gateways setting
            array_splice($settings, $insert_after_index, 0, $mollie_settings);
        }
        else
        {
            // Append Mollie settings
            $settings = array_merge($settings, $mollie_settings);
        }

        return $settings;
    }
}
