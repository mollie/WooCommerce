<?php
class WC_Mollie_Helper_Settings
{
    /**
     * @return string|null
     */
    public function getApiKey ()
    {
        return trim(get_option($this->getSettingId('api_key')));
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
     * Return true if the test API key is used
     * @return bool
     */
    public function isTestMode ()
    {
        $api_key = $this->getApiKey();

        if (!empty($api_key) && preg_match('/^(test)_\w+$/', $api_key))
        {
            return true;
        }

        return false;
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
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields (array $settings)
    {
        $data_helper = WC_Mollie::getDataHelper();
        $content     = '';

        try
        {
            $mollie_methods  = $data_helper->getPaymentMethods();
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

            $icon_not_supported = '<span style="color:red; font-weight:bold; cursor:help;" title="' . __("This payment method is not supported by this plugin. Please check if there is a update available."). '">' . __("Not supported") . '</span>';

            $content .= '<br /><br />' . __('The following payment methods are activated in your Mollie profile:', 'woocommerce-mollie-payments');

            $content .= '<ul style="width: 900px">';
            foreach ($mollie_methods as $payment_method)
            {
                $content .= '<li style="float: left; width: 33%;">';
                $content .= '<img src="' . esc_attr($payment_method->image->normal) . '" alt="' . esc_attr($payment_method->description) . '" title="' . esc_attr($payment_method->description) . '" style="width: 25px; vertical-align: bottom;" />';
                $content .= ' ' . esc_html($payment_method->description);

                // No plugin gateway found with support for this payment method
                if (!isset($mollie_gateways[$payment_method->id]))
                {
                    $content .= ' ' . $icon_not_supported;
                }

                $content .= '</li>';

            }
            $content .= '</ul>';
            $content .= '<div class="clear"></div>';
        }
        catch (WC_Mollie_Exception_InvalidApiKey $e)
        {
            $content = '<span style="color:red; font-weight:bold;">Error: ' . esc_html($e->getMessage()) . '</span>';
        }

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
                'id'                => $this->getSettingId('api_key'),
                'title'             => __('Mollie API key', 'woocommerce-mollie-payments'),
                'type'              => 'text',
                'desc'              => __('The API key is used to connect to Mollie. You can find your API key in your <a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">website profile</a>', 'woocommerce-mollie-payments'),
                'placeholder'       => __('API key should start with live_ or test_'),
                'css'               => 'width: 350px',
                'custom_attributes' => array(
                    'pattern' => '^(live|test)_\w+$',
                ),
            ),
            array(
                'id'      => $this->getSettingId('payment_description'),
                'title'   => __('Description', 'woocommerce'),
                'type'    => 'text',
                'desc'    => sprintf(__('Payment description send to Mollie. Use <code>%%</code> as a placeholder for the order number. Default <code>%s</code>', 'woocommerce-mollie-payments'), $default_payment_description),
                'default' => $default_payment_description,
                'css'     => 'width: 350px',
            ),
            array(
                'id'      => $this->getSettingId('debug'),
                'title'   => __('Debug Log', 'woocommerce'),
                'type'    => 'checkbox',
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
