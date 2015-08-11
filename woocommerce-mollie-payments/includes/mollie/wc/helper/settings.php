<?php
class Mollie_WC_Helper_Settings
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
                . '<div id="message" class="error fade">'
                . ' <strong>' . __('Error', 'woocommerce-mollie-payments') . ':</strong> ' . implode('<br/>', $status->getErrors())
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
        catch (Mollie_WC_Exception_CouldNotConnectToMollie $e)
        {
            $api_status = ''
                . '<p style="font-weight:bold;"><span style="color:red;">Communicating with Mollie failed:</span> ' . esc_html($e->getMessage()) . '</p>'
                . '<p>Please check the following conditions. You can ask your system administrator to help with this.</p>'

                . '<ul style="color: #2D60B0;">'
                . ' <li>Please check if you\'ve inserted your API key correctly.</li>'
                . ' <li>Make sure outside connections to <strong>' . esc_html(Mollie_WC_Helper_Api::getApiEndpoint()) . '</strong> are not blocked.</li>'
                . ' <li>Make sure SSL v3 is disabled on your server. Mollie does not support SSL v3.</li>'
                . ' <li>Make sure your server is up-to-date and the latest security patches have been installed.</li>'
                . '</ul><br/>'

                . '<p>Please contact <a href="mailto:info@mollie.com">info@mollie.com</a> if this still does not fix your problem.</p>';

            $api_status_type = 'error';
        }
        catch (Mollie_WC_Exception_InvalidApiKey $e)
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
            $data_helper     = Mollie_WC_Plugin::getDataHelper();
            $settings_helper = Mollie_WC_Plugin::getSettingsHelper();

            // Is Test mode enabled?
            $test_mode       = $settings_helper->isTestModeEnabled();

            if (isset($_GET['refresh-methods']) && check_admin_referer('refresh-methods'))
            {
                /* Reload active Mollie methods */
                $data_helper->getPaymentMethods($test_mode, $use_cache = false);
            }

            $icon_available     = ' <span style="color: green; cursor: help;" title="' . __('Gateway enabled', 'woocommerce-mollie-payments'). '">' . strtolower(__('Enabled', 'woocommerce-mollie-payments')) . '</span>';
            $icon_no_available  = ' <span style="color: red; cursor: help;" title="' . __('Gateway disabled', 'woocommerce-mollie-payments'). '">' . strtolower(__('Disabled', 'woocommerce-mollie-payments')) . '</span>';

            $content .= '<br /><br />';

            if ($test_mode)
            {
                $content .= '<strong>' . __('Test mode enabled.', 'woocommerce-mollie-payments') . '</strong> ';
            }

            $content .= sprintf(
                /* translators: The surrounding %s's Will be replaced by a link to the Mollie profile */
                __('The following payment methods are activated in your %sMollie profile%s:', 'woocommerce-mollie-payments'),
                '<a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">',
                '</a>'
            );

            $refresh_methods_url = wp_nonce_url(
                add_query_arg(array('refresh-methods' => 1)),
                'refresh-methods'
            ) . '#' . Mollie_WC_Plugin::PLUGIN_ID;

            $content .= ' (<a href="' . esc_attr($refresh_methods_url) . '">' . strtolower(__('Refresh', 'woocommerce-mollie-payments')) . '</a>)';

            $content .= '<ul style="width: 1000px">';

            foreach (Mollie_WC_Plugin::$GATEWAYS as $gateway_classname)
            {
                $gateway = new $gateway_classname;

                if ($gateway instanceof Mollie_WC_Gateway_Abstract)
                {
                    $content .= '<li style="float: left; width: 33%;">';

                    $content .= '<img src="' . esc_attr($gateway->getIconUrl()) . '" alt="' . esc_attr($gateway->getDefaultTitle()) . '" title="' . esc_attr($gateway->getDefaultTitle()) . '" style="width: 25px; vertical-align: bottom;" />';
                    $content .= ' ' . esc_html($gateway->getDefaultTitle());

                    if ($gateway->is_available())
                    {
                        $content .= $icon_available;
                    }
                    else
                    {
                        $content .= $icon_no_available;
                    }

                    $content .= ' <a href="' . $this->getGatewaySettingsUrl($gateway_classname) . '">' . strtolower(__('Edit', 'woocommerce-mollie-payments')) . '</a>';

                    $content .= '</li>';
                }
            }

            $content .= '</ul>';
            $content .= '<div class="clear"></div>';
        }
        catch (Mollie_WC_Exception_InvalidApiKey $e)
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
        wp_register_script('mollie_wc_admin_settings', Mollie_WC_Plugin::getPluginUrl('/assets/js/settings.js'), array('jquery'), Mollie_WC_Plugin::PLUGIN_VERSION);
        wp_enqueue_script('mollie_wc_admin_settings');

        $content = ''
            . $this->getPluginStatus()
            . $this->getMollieMethods();

        /* translators: Default payment description. {order_number} and {order_date} are available tags. */
        $default_payment_description = __('Order {order_number}', 'woocommerce-mollie-payments');
        $payment_description_tags    = '<code>{order_number}</code>, <code>{order_date}</code>';

        $debug_desc = __('Log plugin events.', 'woocommerce-mollie-payments');

        // For WooCommerce 2.2.0+ display view logs link
        if (version_compare(Mollie_WC_Plugin::getStatusHelper()->getWooCommerceVersion(), '2.2.0', ">="))
        {
            $debug_desc .= ' <a href="' . $this->getLogsUrl() . '">' . __('View logs', 'woocommerce-mollie-payments') . '</a>';
        }
        // Display location of log files
        else
        {
            $upload_dir = wp_upload_dir();

            /* translators: Placeholder 1: Location of the log files */
            $debug_desc .= ' ' . sprintf(__('Log files are saved to <code>%s</code>', 'woocommerce-mollie-payments'), defined('WC_LOG_DIR') ? WC_LOG_DIR : $upload_dir['basedir'] . '/wc-logs/');
        }

        // Global Mollie settings
        $mollie_settings = array(
            array(
                'id'    => $this->getSettingId('title'),
                'title' => __('Mollie settings', 'woocommerce-mollie-payments'),
                'type'  => 'title',
                'desc'  => '<p id="' . Mollie_WC_Plugin::PLUGIN_ID . '">' . $content . '</p>'
                         . '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'woocommerce-mollie-payments') . '</p>',
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
                /* translators: Placeholder 1: Default payment description, placeholder 2: list of available tags */
                'desc'    => sprintf(__('Payment description send to Mollie. Default <code>%s</code><br/>You can use the following tags: %s', 'woocommerce-mollie-payments'), $default_payment_description, $payment_description_tags),
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

    /**
     * Called when page 'WooCommerce -> Checkout -> Checkout Options' is saved
     */
    public function onGlobalSettingsSaved ()
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ': Mollie settings saved, delete transients');

        delete_transient(Mollie_WC_Plugin::PLUGIN_ID . '_api_methods_test');
        delete_transient(Mollie_WC_Plugin::PLUGIN_ID . '_api_methods_live');
        delete_transient(Mollie_WC_Plugin::PLUGIN_ID . '_api_issuers_test');
        delete_transient(Mollie_WC_Plugin::PLUGIN_ID . '_api_issuers_live');
    }

    /**
     * @param string $setting
     * @return string
     */
    protected function getSettingId ($setting)
    {
        return Mollie_WC_Plugin::PLUGIN_ID . '_' . trim($setting);
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
