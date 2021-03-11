<?php

class Mollie_WC_Settings_Page_Mollie extends WC_Settings_Page
{
    const FILTER_COMPONENTS_SETTINGS = 'mollie_settings';
    protected $settingsHelper;

    public function __construct(Mollie_WC_Helper_Settings $settingsHelper)
    {
        $this->id = 'mollie_settings';
        $this->label = __('Mollie Settings', 'mollie-payments-for-woocommerce');
        $this->settingsHelper = $settingsHelper;

        add_action(
            'woocommerce_sections_' . $this->id,
            array($this, 'output_sections')
        );
        parent::__construct();
    }

    public function output()
    {
        global $current_section;
        $settings = $this->get_settings($current_section);
        WC_Admin_Settings::output_fields($settings);
    }
    /**
     * Save settings
     *
     * @since 1.0
     */
    public function save()
    {
        global $current_section;

        $settings = $this->get_settings($current_section);
        if ('applepay_button' === $current_section) {
            $data = filter_var_array($_POST,FILTER_SANITIZE_STRING);

            $applepaySettings = [];
            isset($data['enabled']) &&($data['enabled'] === '1') ? $applepaySettings['enabled'] = 'yes'
                : $applepaySettings['enabled'] = 'no';
            isset($data['display_logo'])&&($data['display_logo'] === '1') ?
                $applepaySettings['display_logo'] = 'yes'
                : $applepaySettings['display_logo'] = 'no';
            isset($data['mollie_apple_pay_button_enabled_cart']) && ($data['mollie_apple_pay_button_enabled_cart'] === '1') ?
                $applepaySettings['mollie_apple_pay_button_enabled_cart'] = 'yes'
                : $applepaySettings['mollie_apple_pay_button_enabled_cart'] = 'no';
            isset($data['mollie_apple_pay_button_enabled_product']) && ($data['mollie_apple_pay_button_enabled_product'] === '1') ?
                $applepaySettings['mollie_apple_pay_button_enabled_product'] = 'yes'
                : $applepaySettings['mollie_apple_pay_button_enabled_product'] = 'no';
            isset($data['title']) ? $applepaySettings['title'] = $data['title']
                : $applepaySettings['title'] = '';
            isset($data['description']) ?
                $applepaySettings['description'] = $data['description']
                : $applepaySettings['description'] = '';
            update_option(
                'mollie_wc_gateway_applepay_settings',
                $applepaySettings
            );
        } else {
            WC_Admin_Settings::save_fields($settings);
        }
    }

    public function get_settings($current_section = '')
    {
        $mollieSettings = $this->settingsHelper->addGlobalSettingsFields([]);

        if ('mollie_components' === $current_section) {
            $mollieSettings = $this->sectionSettings(
                $this->componentsFilePath()
            );
        }
        if ('applepay_button' === $current_section) {
            $mollieSettings = $this->sectionSettings($this->applePaySection());
        }
        if ('advanced' === $current_section) {
            $mollieSettings = $this->sectionSettings($this->advancedSectionFilePath());
        }

        /**
         * Filter Component Settings
         *
         * @param array $componentSettings Default components settings for the Credit Card Gateway
         */
        $mollieSettings = apply_filters(
            self::FILTER_COMPONENTS_SETTINGS,
            $mollieSettings
        );

        $mollieSettings = apply_filters(
            'woocommerce_get_settings_' . $this->id,
            $mollieSettings,
            $current_section
        );

        return $mollieSettings;
    }

    /**
     * @param $filePath
     *
     * @return array|mixed
     */
    protected function sectionSettings($filePath)
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $section = include $filePath;

        if (!is_array($section)) {
            $section = [];
        }

        return $section;
    }

    /**
     * @return string
     */
    protected function componentsFilePath()
    {
        return Mollie_WC_Plugin::getPluginPath(
            '/inc/settings/mollie_components.php'
        );
    }

    /**
     * @return string
     */
    protected function applePaySection()
    {
        return Mollie_WC_Plugin::getPluginPath(
            '/inc/settings/mollie_applepay_settings.php'
        );
    }

    /**
     * @return string
     */
    protected function advancedSectionFilePath()
    {
        return Mollie_WC_Plugin::getPluginPath(
            '/inc/settings/mollie_advanced_settings.php'
        );
    }

    /**
     * @return array|mixed|void|null
     */
    public function get_sections()
    {
        $sections = array(
            '' => __('General', 'mollie-payments-for-woocommerce'),
            'mollie_components' => __(
                'Mollie Components',
                'mollie-payments-for-woocommerce'
            ),
            'applepay_button' => __(
                'Apple Pay Button',
                'mollie-payments-for-woocommerce'
            ),
            'advanced' => __('Advanced', 'mollie-payments-for-woocommerce')
        );

        return apply_filters(
            'woocommerce_get_sections_' . $this->id,
            $sections
        );
    }
}
