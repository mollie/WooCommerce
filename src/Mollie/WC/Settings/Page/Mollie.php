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
    public function save() {

        global $current_section;

        $settings = $this->get_settings( $current_section );
        if ('applepay_button' == $current_section){
            $data = $_POST;

            $applepaySettings = [];
            $data['enabled'] === '1'? $applepaySettings['enabled']= 'yes':$applepaySettings['enabled']= 'no';
            $data['display_logo'] === '1'? $applepaySettings['display_logo']= 'yes':$applepaySettings['display_logo']= 'no';
            $data['mollie_apple_pay_button_enabled'] === '1'? $applepaySettings['mollie_apple_pay_button_enabled']= 'yes':$applepaySettings['mollie_apple_pay_button_enabled']= 'no';
            $data['title']? $applepaySettings['title']= $data['title']:$applepaySettings['title']= '';
            $data['description']? $applepaySettings['description']= $data['description']:$applepaySettings['description']= '';
            update_option('mollie_wc_gateway_applepay_settings', $applepaySettings);

        }else{
            WC_Admin_Settings::save_fields( $settings );

        }


    }

    public function get_settings($current_section = '')
    {
        $mollieSettings = $this->settingsHelper->addGlobalSettingsFields([]);

        if ('mollie_components' == $current_section) {
            $mollieSettings = $this->sectionSettings(
                $this->componentsFilePath()
            );
        }
        if ('applepay_button' == $current_section) {
            $mollieSettings = $this->sectionSettings($this->applePaySection());
        }
        if ('advanced' == $current_section) {
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
     * @return array
     */
    protected function applePaySection()
    {
        /*$gateway = new Mollie_WC_Gateway_Applepay();

        $title = $gateway->method_title;
        $description = $gateway->method_description;
        $pluginId = Mollie_WC_Plugin::PLUGIN_ID;
        $applePayOption = get_option('mollie_wc_gateway_applepay_settings');

        return [
            [
                'id' => $title,
                'title' => __($title, 'mollie-payments-for-woocommerce'),
                'type' => 'title',
                'desc' => $description,
            ],
            'enabled' => [
                'title' => __(
                    'Enable/Disable',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => sprintf(
                    __('Enable %s', 'mollie-payments-for-woocommerce'),
                    $title
                ),
                'default' => $applePayOption ? $applePayOption['enabled']
                    : 'yes'
            ],
            'title' => [
                'title' => __('Title', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(
                    __(
                        'This controls the title which the user sees during checkout. Default <code>%s</code>',
                        'mollie-payments-for-woocommerce'
                    ),
                    $title
                ),
                'default' => $applePayOption ? $applePayOption['title']
                    : $title,
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
                'default' => $applePayOption ? $applePayOption['display_logo']
                    : 'yes',
            ],
            'description' => [
                'title' => __('Description', 'mollie-payments-for-woocommerce'),
                'type' => 'textarea',
                'description' => sprintf(
                    __(
                        'Payment method description that the customer will see on your checkout. Default <code>%s</code>',
                        'mollie-payments-for-woocommerce'
                    ),
                    $description
                ),
                'default' => $applePayOption ? $applePayOption['description']
                    : $description,
                'desc_tip' => true,
            ],
            [
                'id' => $pluginId . '_' . 'sectionend',
                'type' => 'sectionend',
            ],
            [
                'id' => $pluginId . '_' . 'title',
                'title' => __(
                    'Apple Pay button settings',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'title',
                'desc' =>
                    '<p>' . __(
                        'The following options are required to use the Apple Pay button',
                        'mollie-payments-for-woocommerce'
                    ) . '</p>',
            ],
            'mollie_apple_pay_button_enabled' => [
                'type' => 'checkbox',
                'title' => __(
                    'Enable Apple Pay Button',
                    'mollie-payments-for-woocommerce'
                ),
                'description' => __(
                    'Enable the Apple Pay direct buy button',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => $applePayOption
                    ? $applePayOption['mollie_apple_pay_button_enabled'] : 'no',
            ],
            [
                'id' => $pluginId . '_' . 'sectionend',
                'type' => 'sectionend',
            ]
        ];*/
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
