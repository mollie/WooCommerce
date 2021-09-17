<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Utils\Data;
use WC_Admin_Settings;
use WC_Gateway_BACS;
use WC_Settings_Page;

class MollieSettingsPage extends WC_Settings_Page
{
    const FILTER_COMPONENTS_SETTINGS = 'mollie_settings';
    protected $settingsHelper;

    /**
     * @var string
     */
    protected $pluginPath;
    /**
     * @var array
     */
    protected $registeredGateways;
    /**
     * @var bool
     */
    protected $isTestModeEnabled;
    /**
     * @var Data
     */
    protected $dataHelper;

    public function __construct(Settings $settingsHelper, string $pluginPath, array $gateways, bool $isTestModeEnabled, Data $dataHelper)
    {
        $this->id = 'mollie_settings';
        $this->label = __('Mollie Settings', 'mollie-payments-for-woocommerce');
        $this->settingsHelper = $settingsHelper;
        $this->pluginPath = $pluginPath;
        $this->registeredGateways = $gateways;
        $this->isTestModeEnabled = $isTestModeEnabled;
        $this->dataHelper = $dataHelper;

        add_action(
            'woocommerce_sections_' . $this->id,
            [$this, 'output_sections']
        );
        parent::__construct();
    }

    public function output()
    {
        global $current_section;
        $settings = $this->get_settings($current_section);
        $settings = $this->hideKeysIntoStars($settings);

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
            $this->saveApplePaySettings();
        } else {
            $settings = $this->saveApiKeys($settings);
            WC_Admin_Settings::save_fields($settings);
        }
    }

    public function get_settings($current_section = '')
    {
        $mollieSettings = $this->addGlobalSettingsFields([]);

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
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields(array $settings)
    {
        wp_register_script('mollie_wc_admin_settings', $this->settingsHelper->pluginUrl . '/public/js/settings.min.js', ['jquery'], $this->settingsHelper->pluginVersion);
        wp_enqueue_script('mollie_wc_admin_settings');

        $presentationText = __('Quickly integrate all major payment methods in WooCommerce, wherever you need them.', 'mollie-payments-for-woocommerce');
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
            . $this->settingsHelper->getPluginStatus()
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
        $mollie_settings = [
            [
                'id' => $this->settingsHelper->getSettingId('title'),
                'title' => __('Mollie Settings', 'mollie-payments-for-woocommerce'),
                'type' => 'title',
                'desc' => '<p id="' . $this->settingsHelper->pluginId . '">' . $content . '</p>'
                    . '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'mollie-payments-for-woocommerce') . '</p>',
            ],
            [
                'id' => $this->settingsHelper->getSettingId('live_api_key'),
                'title' => __('Live API key', 'mollie-payments-for-woocommerce'),
                'default' => '',
                'type' => 'text',
                'desc' => sprintf(
                /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%1$s</strong> API key in your %2$sMollie profile%3$s', 'mollie-payments-for-woocommerce'),
                    'live',
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                ),
                'css' => 'width: 350px',
                'placeholder' => $live_placeholder = __('Live API key should start with live_', 'mollie-payments-for-woocommerce'),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('test_mode_enabled'),
                'title' => __('Enable test mode', 'mollie-payments-for-woocommerce'),
                'default' => 'no',
                'type' => 'checkbox',
                'desc_tip' => __('Enable test mode if you want to test the plugin without using real payments.', 'mollie-payments-for-woocommerce'),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('test_api_key'),
                'title' => __('Test API key', 'mollie-payments-for-woocommerce'),
                'default' => '',
                'type' => 'text',
                'desc' => sprintf(
                /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%1$s</strong> API key in your %2$sMollie profile%3$s', 'mollie-payments-for-woocommerce'),
                    'test',
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                ),
                'css' => 'width: 350px',
                'placeholder' => $test_placeholder = __('Test API key should start with test_', 'mollie-payments-for-woocommerce'),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('debug'),
                'title' => __('Debug Log', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'desc' => $debug_desc,
                'default' => 'yes',
            ],
            [
                'id' => $this->settingsHelper->getSettingId('sectionend'),
                'type' => 'sectionend',
            ],
        ];

        return $this->mergeSettings($settings, $mollie_settings);
    }

    public function getMollieMethods()
    {
        $content = '';

        $data_helper = $this->dataHelper;

        // Is Test mode enabled?
        $test_mode = $this->isTestModeEnabled;
        $apiKey = $this->settingsHelper->getApiKey($test_mode);

        if (isset($_GET['refresh-methods']) && wp_verify_nonce($_GET['nonce_mollie_refresh_methods'], 'nonce_mollie_refresh_methods')) {
            /* Reload active Mollie methods */
            $data_helper->getAllPaymentMethods($apiKey, $test_mode, $use_cache = false);
        }

        $icon_available = ' <span style="color: green; cursor: help;" title="' . __('Gateway enabled', 'mollie-payments-for-woocommerce') . '">' . strtolower(__('Enabled', 'mollie-payments-for-woocommerce')) . '</span>';
        $icon_no_available = ' <span style="color: red; cursor: help;" title="' . __('Gateway disabled', 'mollie-payments-for-woocommerce') . '">' . strtolower(__('Disabled', 'mollie-payments-for-woocommerce')) . '</span>';

        $content .= '<br /><br />';
        $content .= '<div style="width:1000px;height:350px; background:white; padding:10px; margin-top:10px;">';

        if ($test_mode) {
            $content .= '<strong>' . __('Test mode enabled.', 'mollie-payments-for-woocommerce') . '</strong> ';
        }

        $content .= sprintf(
        /* translators: The surrounding %s's Will be replaced by a link to the Mollie profile */
            __('The following payment methods are activated in your %1$sMollie profile%2$s:', 'mollie-payments-for-woocommerce'),
            '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
            '</a>'
        );

        // Set a "refresh" link so payment method status can be refreshed from Mollie API
        $nonce_mollie_refresh_methods = wp_create_nonce('nonce_mollie_refresh_methods');
        $refresh_methods_url = add_query_arg([ 'refresh-methods' => 1, 'nonce_mollie_refresh_methods' => $nonce_mollie_refresh_methods ]);

        $content .= ' (<a href="' . esc_attr($refresh_methods_url) . '">' . strtolower(__('Refresh', 'mollie-payments-for-woocommerce')) . '</a>)';

        $content .= '<ul style="width: 1000px; padding:20px 0 0 10px">';

        $mollieGateways = $this->registeredGateways;
        foreach ($mollieGateways as $gateway) {
            if ($gateway instanceof MolliePaymentGateway) {
                $content .= '<li style="float: left; width: 32%; height:32px;">';
                $content .= $gateway->getIconUrl();
                $content .= ' ' . esc_html($gateway->paymentMethod->getProperty('defaultTitle'));

                if ($gateway->is_available()) {
                    $content .= $icon_available;
                } else {
                    $content .= $icon_no_available;
                }

                $content .= ' <a href="' . $this->getGatewaySettingsUrl($gateway->id) . '">' . strtolower(__('Edit', 'mollie-payments-for-woocommerce')) . '</a>';

                $content .= '</li>';
            }
        }

        $content .= '</ul></div>';
        $content .= '<div class="clear"></div>';

        // Make sure users also enable iDEAL when they enable SEPA Direct Debit
        // iDEAL is needed for the first payment of subscriptions with SEPA Direct Debit
        $content = $this->checkDirectDebitStatus($content);

        // Advice users to use bank transfer via Mollie, not
        // WooCommerce default BACS method
        $content = $this->checkMollieBankTransferNotBACS($content);

        // Warn users that all default WooCommerce checkout fields
        // are required to accept Klarna as payment method
        $content = $this->warnAboutRequiredCheckoutFieldForKlarna($content);

        return $content;
    }

    /**
     * @param $content
     *
     * @return string
     */
    protected function checkDirectDebitStatus($content): string
    {
        $ideal_gateway = $this->registeredGateways["mollie_wc_gateway_ideal"];
        $sepa_gateway = $this->registeredGateways["mollie_wc_gateway_directdebit"];

        if (( class_exists('WC_Subscription') ) && ( $ideal_gateway->is_available() ) && ( ! $sepa_gateway->is_available() )) {
            $warning_message = __('You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL and/or other "first" payment methods.', 'mollie-payments-for-woocommerce');

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
    protected function checkMollieBankTransferNotBACS($content)
    {
        $woocommerce_banktransfer_gateway = new WC_Gateway_BACS();

        if ($woocommerce_banktransfer_gateway->is_available()) {
            $content .= '<div class="notice notice-warning is-dismissible"><p>';
            $content .= __('You have the WooCommerce default Direct Bank Transfer (BACS) payment gateway enabled in WooCommerce. Mollie strongly advices only using Bank Transfer via Mollie and disabling the default WooCommerce BACS payment gateway to prevent possible conflicts.', 'mollie-payments-for-woocommerce');
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
    protected function warnAboutRequiredCheckoutFieldForKlarna($content)
    {
        $woocommerce_klarnapaylater_gateway = isset($this->registeredGateways["mollie_wc_gateway_klarnapaylater"]) ? $this->registeredGateways["mollie_wc_gateway_klarnapaylater"] : false;
        $woocommerce_klarnasliceit_gateway = isset($this->registeredGateways["mollie_wc_gateway_klarnadliceit"]) ? $this->registeredGateways["mollie_wc_gateway_klarnasliceit"] : false;

        if ($woocommerce_klarnapaylater_gateway && $woocommerce_klarnapaylater_gateway->is_available() || $woocommerce_klarnasliceit_gateway && $woocommerce_klarnasliceit_gateway->is_available()) {
            $content .= '<div class="notice notice-warning is-dismissible"><p>';
            $content .= sprintf(
                __(
                    'You have activated Klarna. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. For more information, go to %1$1sKlarna Pay Later documentation%2$2s or  %3$3sKlarna Slice it documentation%4$4s',
                    'mollie-payments-for-woocommerce'
                ),
                '<a href="https://github.com/mollie/WooCommerce/wiki/Setting-up-Klarna-Pay-later-gateway">',
                '</a>',
                '<a href=" https://github.com/mollie/WooCommerce/wiki/Setting-up-Klarna-Slice-it-gateway">',
                '</a>'
            );
            $content .= '</p></div> ';

            return $content;
        }

        return $content;
    }


    /**
     * @param array $settings
     * @param array $mollie_settings
     * @return array
     */
    protected function mergeSettings(array $settings, array $mollie_settings): array
    {
        $new_settings = [];
        $mollie_settings_merged = false;

        // Find payment gateway options index
        foreach ($settings as $index => $setting) {
            if (isset($setting['id']) && $setting['id'] == 'payment_gateways_options'
                && (!isset($setting['type']) || $setting['type'] != 'sectionend')
            ) {
                $new_settings = array_merge($new_settings, $mollie_settings);
                $mollie_settings_merged = true;
            }

            $new_settings[] = $setting;
        }

        // Mollie settings not merged yet, payment_gateways_options not found
        if (!$mollie_settings_merged) {
            // Append Mollie settings
            $new_settings = array_merge($new_settings, $mollie_settings);
        }

        return $new_settings;
    }

    /**
     * @param string $gateway_class_name
     * @return string
     */
    protected function getGatewaySettingsUrl($gateway_class_name): string
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($gateway_class_name)));
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
        return $this->pluginPath . '/inc/settings/mollie_components.php';
    }

    /**
     * @return string
     */
    protected function applePaySection()
    {
        return $this->pluginPath . '/inc/settings/mollie_applepay_settings.php';
    }

    /**
     * @return string
     */
    protected function advancedSectionFilePath()
    {
        return $this->pluginPath . '/inc/settings/mollie_advanced_settings.php';
    }

    /**
     * @return array|mixed|void|null
     */
    public function get_sections()
    {
        $sections = [
            '' => __('General', 'mollie-payments-for-woocommerce'),
            'mollie_components' => __(
                'Mollie Components',
                'mollie-payments-for-woocommerce'
            ),
            'applepay_button' => __(
                'Apple Pay Button',
                'mollie-payments-for-woocommerce'
            ),
            'advanced' => __('Advanced', 'mollie-payments-for-woocommerce'),
        ];

        return apply_filters(
            'woocommerce_get_sections_' . $this->id,
            $sections
        );
    }

    /**
     * @param $settings
     *
     * @return array
     */
    protected function hideKeysIntoStars($settings)
    {
        $liveKeyName = 'mollie-payments-for-woocommerce_live_api_key';
        $testKeyName = 'mollie-payments-for-woocommerce_test_api_key';
        $liveValue = get_option($liveKeyName);
        $testValue = get_option($testKeyName);

        foreach ($settings as $key => $setting) {
            if (($setting['id']
                    === $liveKeyName
                    && $liveValue)
                || ($setting['id']
                    === $testKeyName
                    && $testValue)
            ) {
                $settings[$key]['value'] = '**********';
            }
        }
        return $settings;
    }

    /**
     * @param $settings
     *
     * @return array
     */
    protected function saveApiKeys($settings)
    {
        $liveKeyName = 'mollie-payments-for-woocommerce_live_api_key';
        $testKeyName = 'mollie-payments-for-woocommerce_test_api_key';
        $liveValueInDb = get_option($liveKeyName);
        $testValueInDb = get_option($testKeyName);
        $postedLiveValue = isset($_POST[$liveKeyName])? sanitize_text_field($_POST[$liveKeyName]):'';
        $postedTestValue = isset($_POST[$testKeyName])? sanitize_text_field($_POST[$testKeyName]):'';

        foreach ($settings as $setting) {
            if ($setting['id']
                === $liveKeyName
                && $liveValueInDb
            ) {
                if ($postedLiveValue === '**********') {
                    $_POST[$liveKeyName] = $liveValueInDb;
                } else {
                    $pattern = '/^live_\w{30,}$/';
                    $this->validateApiKeyOrRemove(
                        $pattern,
                        $postedLiveValue,
                        $liveKeyName
                    );
                }
            } elseif ($setting['id']
                === $testKeyName
                && $testValueInDb
            ) {
                if ($postedTestValue === '**********') {
                    $_POST[$testKeyName] = $testValueInDb;
                } else {
                    $pattern = '/^test_\w{30,}$/';
                    $this->validateApiKeyOrRemove(
                        $pattern,
                        $postedTestValue,
                        $testKeyName
                    );
                }
            }
        }
        return $settings;
    }

    protected function saveApplePaySettings()
    {
        $data = filter_var_array($_POST, FILTER_SANITIZE_STRING);

        $applepaySettings = [];
        isset($data['enabled']) && ($data['enabled'] === '1') ?
            $applepaySettings['enabled'] = 'yes'
            : $applepaySettings['enabled'] = 'no';
        isset($data['display_logo']) && ($data['display_logo'] === '1') ?
            $applepaySettings['display_logo'] = 'yes'
            : $applepaySettings['display_logo'] = 'no';
        isset($data['mollie_apple_pay_button_enabled_cart'])
        && ($data['mollie_apple_pay_button_enabled_cart'] === '1') ?
            $applepaySettings['mollie_apple_pay_button_enabled_cart'] = 'yes'
            : $applepaySettings['mollie_apple_pay_button_enabled_cart'] = 'no';
        isset($data['mollie_apple_pay_button_enabled_product'])
        && ($data['mollie_apple_pay_button_enabled_product'] === '1')
            ?
            $applepaySettings['mollie_apple_pay_button_enabled_product'] = 'yes'
            :
            $applepaySettings['mollie_apple_pay_button_enabled_product'] = 'no';
        isset($data['title']) ? $applepaySettings['title'] = $data['title']
            : $applepaySettings['title'] = '';
        isset($data['description']) ?
            $applepaySettings['description'] = $data['description']
            : $applepaySettings['description'] = '';
        update_option(
            'mollie_wc_gateway_applepay_settings',
            $applepaySettings
        );
    }

    /**
     * @param       $pattern
     * @param       $value
     * @param       $keyName
     *
     */
    protected function validateApiKeyOrRemove($pattern, $value, $keyName)
    {
        $hasApiFormat = preg_match($pattern, $value);
        if (!$hasApiFormat) {
            unset($_POST[$keyName]);
        }
    }
}
