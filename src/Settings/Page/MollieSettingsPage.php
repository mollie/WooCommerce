<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\PaymentMethods\Constants;
use WC_Admin_Settings;
use WC_Gateway_BACS;
use WC_Settings_Page;

class MollieSettingsPage extends WC_Settings_Page
{
    public const FILTER_COMPONENTS_SETTINGS = 'mollie_settings';
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
    /**
     * @var array
     */
    protected $paymentMethods;

    public function __construct(
        Settings $settingsHelper,
        string $pluginPath,
        array $gateways,
        array $paymentMethods,
        bool $isTestModeEnabled,
        Data $dataHelper
    ) {

        $this->id = 'mollie_settings';
        $this->label = __('Mollie Settings', 'mollie-payments-for-woocommerce');
        $this->settingsHelper = $settingsHelper;
        $this->pluginPath = $pluginPath;
        $this->registeredGateways = $gateways;
        $this->isTestModeEnabled = $isTestModeEnabled;
        $this->dataHelper = $dataHelper;
        $this->paymentMethods = $paymentMethods;
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

    public function get_settings($currentSection = '')
    {
        $mollieSettings = $this->addGlobalSettingsFields([]);

        if ('mollie_components' === $currentSection) {
            $mollieSettings = $this->sectionSettings(
                $this->componentsFilePath()
            );
        }
        if ('applepay_button' === $currentSection) {
            $mollieSettings = $this->sectionSettings($this->applePaySection());
        }
        if ('advanced' === $currentSection) {
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

        return apply_filters(
            'woocommerce_get_settings_' . $this->id,
            $mollieSettings,
            $currentSection
        );
    }

    /**
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields(array $settings): array
    {
        $presentationText = __(
            'Quickly integrate all major payment methods in WooCommerce, wherever you need them.',
            'mollie-payments-for-woocommerce'
        );
        $presentationText .= __(
            ' Simply drop them ready-made into your WooCommerce webshop with this powerful plugin by Mollie.',
            'mollie-payments-for-woocommerce'
        );
        $presentationText .= __(
            ' Mollie is dedicated to making payments better for WooCommerce. ',
            'mollie-payments-for-woocommerce'
        );
        $presentationText .= '<p>' . __(
            'Please go to',
            'mollie-payments-for-woocommerce'
        ) . '<a href="https://my.mollie.com/dashboard/signup?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner">' . __(
            ' the signup page',
            'mollie-payments-for-woocommerce'
        ) . '</a> ';
        $presentationText .= __(
            ' to create a new Mollie account and start receiving payments in a couple of minutes. ',
            'mollie-payments-for-woocommerce'
        );
        $presentationText .= __(
            'Contact ',
            'mollie-payments-for-woocommerce'
        ) . '<a href="mailto:info@mollie.com">info@mollie.com</a>';
        $presentationText .= __(
            ' if you have any questions or comments about this plugin.',
            'mollie-payments-for-woocommerce'
        ) . '</p>';
        $presentationText .= '<p style="border-left: 4px solid black; padding: 8px; height:32px; font-weight:bold; font-size: medium;">' . __(
            'Our pricing is always per transaction. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.',
            'mollie-payments-for-woocommerce'
        ) . '</p>';

        $presentation = ''
            . '<div style="width:1000px"><div style="font-weight:bold;"><a href="https://help.mollie.com/hc/en-us/sections/12858723658130-Mollie-for-WooCommerce">' . __(
                'Plugin Documentation',
                'mollie-payments-for-woocommerce'
            ) . '</a> | <a href="https://www.mollie.com/contact/merchants">' . __(
                'Contact Support',
                'mollie-payments-for-woocommerce'
            ) . '</a></div></div>'
            . '<span></span>'
            . '<div id="" class="" style="width: 1000px; padding:5px 0 0 10px"><p>' . $presentationText . '</p></div>';

        $content = ''
            . $presentation
            . $this->settingsHelper->getPluginStatus()
            . $this->getMollieMethods();

        $debugDesc = __('Log plugin events.', 'mollie-payments-for-woocommerce');

        // Display location of log files


        $debugDesc .= ' ' . sprintf(
            /* translators: Placeholder 1: Location of the log files */
            __(
                'Log files are saved to <code>%s</code>',
                'mollie-payments-for-woocommerce'
            ),
            defined('WC_LOG_DIR') ? WC_LOG_DIR
                    : WC()->plugin_path() . '/logs/'
        );

        // Global Mollie settings
        $mollieSettings = [
            [
                'id' => $this->settingsHelper->getSettingId('title'),
                'title' => __('Mollie Settings', 'mollie-payments-for-woocommerce'),
                'type' => 'title',
                'desc' => '<p id="' . $this->dataHelper->getPluginId() . '">' . $content . '</p>'
                    . '<p>' . __(
                        'The following options are required to use the plugin and are used by all Mollie payment methods',
                        'mollie-payments-for-woocommerce'
                    ) . '</p>',
            ],
            [
                'id' => $this->settingsHelper->getSettingId('live_api_key'),
                'title' => __('Live API key', 'mollie-payments-for-woocommerce'),
                'default' => '',
                'type' => 'text',
                'desc' => sprintf(
                /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __(
                        'The API key is used to connect to Mollie. You can find your <strong>%1$s</strong> API key in your %2$sMollie account%3$s',
                        'mollie-payments-for-woocommerce'
                    ),
                    'live',
                    '<a href="https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
                    '</a>'
                ),
                'css' => 'width: 350px',
                'placeholder' => __(
                    'Live API key should start with live_',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('test_mode_enabled'),
                'title' => __('Enable test mode', 'mollie-payments-for-woocommerce'),
                'default' => 'no',
                'type' => 'checkbox',
                'desc_tip' => __(
                    'Enable test mode if you want to test the plugin without using real payments.',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('test_api_key'),
                'title' => __('Test API key', 'mollie-payments-for-woocommerce'),
                'default' => '',
                'type' => 'text',
                'desc' => sprintf(
                /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __(
                        'The API key is used to connect to Mollie. You can find your <strong>%1$s</strong> API key in your %2$sMollie account%3$s',
                        'mollie-payments-for-woocommerce'
                    ),
                    'test',
                    '<a href="https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
                    '</a>'
                ),
                'css' => 'width: 350px',
                'placeholder' => __(
                    'Test API key should start with test_',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('debug'),
                'title' => __('Debug Log', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'desc' => $debugDesc,
                'default' => 'yes',
            ],
            [
                'id' => $this->settingsHelper->getSettingId('sectionend'),
                'type' => 'sectionend',
            ],
        ];

        return $this->mergeSettings($settings, $mollieSettings);
    }
    /**
     * @return string
     */
    public function getMollieMethods(): string
    {
        $content = '';

        $dataHelper = $this->dataHelper;

        // Is Test mode enabled?
        $testMode = $this->isTestModeEnabled;
        $apiKey = $this->settingsHelper->getApiKey();

        if (
            isset($_GET['refresh-methods']) &&
            isset($_GET['nonce_mollie_refresh_methods']) &&
            wp_verify_nonce(
                filter_input(INPUT_GET, 'nonce_mollie_refresh_methods', FILTER_SANITIZE_SPECIAL_CHARS),
                'nonce_mollie_refresh_methods'
            )
        ) {
            /* Reload active Mollie methods */
            $methods = $dataHelper->getAllPaymentMethods($apiKey, $testMode, false);
            foreach ($methods as $key => $method) {
                $methods['mollie_wc_gateway_' . $method['id']] = $method;
                unset($methods[$key]);
            }
            $this->registeredGateways = $methods;
        }
        if (
            isset($_GET['cleanDB-mollie']) && wp_verify_nonce(
                filter_input(INPUT_GET, 'nonce_mollie_cleanDb', FILTER_SANITIZE_SPECIAL_CHARS),
                'nonce_mollie_cleanDb'
            )
        ) {
            $cleaner = $this->settingsHelper->cleanDb();
            $cleaner->cleanAll();
            //set default settings
            foreach ($this->paymentMethods as $paymentMethod) {
                $paymentMethod->getSettings();
            }
        }

        $iconAvailable = ' <span style="color: green; cursor: help;" title="' . __(
            'Gateway enabled',
            'mollie-payments-for-woocommerce'
        ) . '">' . strtolower(__('Enabled', 'mollie-payments-for-woocommerce')) . '</span>';
        $iconNoAvailable = ' <span style="color: red; cursor: help;" title="' . __(
            'Gateway disabled',
            'mollie-payments-for-woocommerce'
        ) . '">' . strtolower(__('Disabled', 'mollie-payments-for-woocommerce')) . '</span>';

        $content .= '<br /><br />';
        $content .= '<div id="mol-icons-container">';

        if ($testMode) {
            $content .= '<strong>' . __('Test mode enabled.', 'mollie-payments-for-woocommerce') . '</strong> ';
        }

        $content .= sprintf(
        /* translators: The surrounding %s's Will be replaced by a link to the Mollie profile */
            __(
                'The following payment methods are activated in your %1$sMollie profile%2$s:',
                'mollie-payments-for-woocommerce'
            ),
            '<a href="https://my.mollie.com/dashboard/settings/profiles?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
            '</a>'
        );

        // Set a "refresh" link so payment method status can be refreshed from Mollie API
        $nonce_mollie_refresh_methods = wp_create_nonce('nonce_mollie_refresh_methods');
        $refresh_methods_url = add_query_arg(
            ['refresh-methods' => 1, 'nonce_mollie_refresh_methods' => $nonce_mollie_refresh_methods]
        );

        $content .= ' (<a href="' . esc_url($refresh_methods_url) . '">' . strtolower(
            __('Refresh', 'mollie-payments-for-woocommerce')
        ) . '</a>)';

        $content .= '<ul class="mol-responsive-table-3">';

        $mollieGateways = $this->registeredGateways;//this are the gateways enabled
        $paymentMethods = $this->paymentMethods;
        if (empty($mollieGateways)) {
            $content .= '<li>';
            $content .= __("No payment methods available", "mollie-payments-for-woocommerce");
            $content .= '</li></ul></div>';
            $content .= '<div class="clear"></div>';
            return $content;
        }
        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodId = $paymentMethod->getProperty('id');
            $gatewayKey = 'mollie_wc_gateway_' . $paymentMethodId;
            $paymentMethodEnabledAtMollie = array_key_exists($gatewayKey, $mollieGateways);
            $content .= '<li>';
            $content .= $paymentMethod->getIconUrl();
            $content .= ' ' . esc_html($paymentMethod->title());
            if ($paymentMethodEnabledAtMollie) {
                $content .= $iconAvailable;
                $content .= ' <a href="' . $this->getGatewaySettingsUrl($gatewayKey) . '">' . strtolower(
                    __('Edit', 'mollie-payments-for-woocommerce')
                ) . '</a>';

                $content .= '</li>';
                continue;
            }
            $content .= $iconNoAvailable;
            $content .= ' <a href="https://my.mollie.com/dashboard/settings/profiles?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">' . strtolower(
                __('Activate', 'mollie-payments-for-woocommerce')
            ) . '</a>';

            $content .= '</li>';
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

        // Warn users that all default WooCommerce checkout fields
        // and billing company name
        // are required to accept Billie as payment method
        $content = $this->warnAboutRequiredCheckoutFieldForBillie($content);

        return $content;
    }

    /**
     * @param string $gateway_class_name
     * @return string
     */
    protected function getGatewaySettingsUrl(string $gateway_class_name): string
    {
        return admin_url(
            'admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($gateway_class_name))
        );
    }

    /**
     * @param $content
     *
     * @return string
     */
    protected function checkDirectDebitStatus($content): string
    {
        $hasCustomSepaSettings = $this->paymentMethods["directdebit"]->getProperty('enabled') !== false;
        $isSepaEnabled = !$hasCustomSepaSettings || $this->paymentMethods["directdebit"]->getProperty('enabled') === 'yes';
        $sepaGatewayAllowed = !empty($this->registeredGateways["mollie_wc_gateway_directdebit"]);
        if ($sepaGatewayAllowed && !$isSepaEnabled) {
            $warning_message = __(
                "You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL and/or other 'first' payment methods.",
                'mollie-payments-for-woocommerce'
            );

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
    protected function checkMollieBankTransferNotBACS($content): string
    {
        $woocommerce_banktransfer_gateway = new WC_Gateway_BACS();

        if ($woocommerce_banktransfer_gateway->is_available()) {
            $content .= '<div class="notice notice-warning is-dismissible"><p>';
            $content .= __(
                'You have the WooCommerce default Direct Bank Transfer (BACS) payment gateway enabled in WooCommerce. Mollie strongly advices only using Bank Transfer via Mollie and disabling the default WooCommerce BACS payment gateway to prevent possible conflicts.',
                'mollie-payments-for-woocommerce'
            );
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
    protected function warnAboutRequiredCheckoutFieldForKlarna($content): string
    {
        $isKlarnaEnabled = $this->isKlarnaEnabled();
        if ($isKlarnaEnabled) {
            $content .= '<div class="notice notice-warning is-dismissible"><p>';
            $content .= sprintf(
            /* translators: Placeholder 1: Opening link tag. Placeholder 2: Closing link tag. Placeholder 3: Opening link tag. Placeholder 4: Closing link tag. */
                __(
                    'You have activated Klarna. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. For more information, go to %1$sKlarna Pay Later documentation%2$s or  %3$sKlarna Slice it documentation%4$s',
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
            if (
                isset($setting['id']) && $setting['id'] === 'payment_gateways_options'
                && (!isset($setting['type']) || $setting['type'] !== 'sectionend')
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
    protected function componentsFilePath(): string
    {
        return $this->pluginPath . '/inc/settings/mollie_components.php';
    }

    /**
     * @return string
     */
    protected function applePaySection(): string
    {
        return $this->pluginPath . '/inc/settings/mollie_applepay_settings.php';
    }

    /**
     * @return string
     */
    protected function advancedSectionFilePath(): string
    {
        return $this->pluginPath . '/inc/settings/mollie_advanced_settings.php';
    }

    /**
     * @param $settings
     *
     * @return array
     */
    protected function hideKeysIntoStars($settings): array
    {
        $liveKeyName = 'mollie-payments-for-woocommerce_live_api_key';
        $testKeyName = 'mollie-payments-for-woocommerce_test_api_key';
        $liveValue = get_option($liveKeyName);
        $testValue = get_option($testKeyName);

        foreach ($settings as $key => $setting) {
            if (
                ($setting['id']
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

    protected function saveApplePaySettings()
    {
        $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        $isNonceValid = wp_verify_nonce(
            $nonce,
            'woocommerce-settings'
        );
        if (!$isNonceValid) {
            return;
        }
        $data = filter_var_array($_POST, FILTER_SANITIZE_SPECIAL_CHARS);

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
     * @param $settings
     *
     * @return array
     */
    protected function saveApiKeys($settings)
    {
        $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        $isNonceValid = wp_verify_nonce(
            $nonce,
            'woocommerce-settings'
        );
        if (!$isNonceValid) {
            return $settings;
        }
        $liveKeyName = 'mollie-payments-for-woocommerce_live_api_key';
        $testKeyName = 'mollie-payments-for-woocommerce_test_api_key';
        $liveValueInDb = get_option($liveKeyName);
        $testValueInDb = get_option($testKeyName);
        $postedLiveValue = isset($_POST[$liveKeyName]) ? sanitize_text_field(wp_unslash($_POST[$liveKeyName])) : '';
        $postedTestValue = isset($_POST[$testKeyName]) ? sanitize_text_field(wp_unslash($_POST[$testKeyName])) : '';

        foreach ($settings as $setting) {
            if (
                $setting['id']
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
            } elseif (
                $setting['id']
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

    /**
     * @param       $pattern
     * @param       $value
     * @param       $keyName
     *
     */
    protected function validateApiKeyOrRemove($pattern, $value, $keyName)
    {
        $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        $isNonceValid = wp_verify_nonce(
            $nonce,
            'woocommerce-settings'
        );
        if (!$isNonceValid) {
            return;
        }
        $hasApiFormat = preg_match($pattern, $value);
        if (!$hasApiFormat) {
            unset($_POST[$keyName]);
        }
    }

    /**
     * @return array|mixed|void|null
     */
    public function get_sections()
    {
        $isAppleEnabled = array_key_exists('mollie_wc_gateway_applepay', $this->registeredGateways);
        $sections = [
            '' => __('General', 'mollie-payments-for-woocommerce'),
            'mollie_components' => __(
                'Mollie Components',
                'mollie-payments-for-woocommerce'
            ),
            'advanced' => __('Advanced', 'mollie-payments-for-woocommerce'),
        ];
        if ($isAppleEnabled) {
            $sections['applepay_button'] = __(
                'Apple Pay Button',
                'mollie-payments-for-woocommerce'
            );
        }

        return apply_filters(
            'woocommerce_get_sections_' . $this->id,
            $sections
        );
    }

    /**
     * @return bool
     */
    protected function isKlarnaEnabled(): bool
    {
        $klarnaGateways = [Constants::KLARNAPAYLATER, Constants::KLARNASLICEIT, Constants::KLARNAPAYNOW, Constants::KLARNA];
        $isKlarnaEnabled = false;
        foreach ($klarnaGateways as $klarnaGateway) {
            if (
                array_key_exists('mollie_wc_gateway_' . $klarnaGateway, $this->registeredGateways)
                && array_key_exists($klarnaGateway, $this->paymentMethods)
                && $this->paymentMethods[$klarnaGateway]->getProperty('enabled') === 'yes'
            ) {
                $isKlarnaEnabled = true;
                break;
            }
        }
        return $isKlarnaEnabled;
    }

    private function warnAboutRequiredCheckoutFieldForBillie(string $content)
    {
        $isBillieEnabled = array_key_exists('mollie_wc_gateway_billie', $this->registeredGateways)
            && array_key_exists('billie', $this->paymentMethods)
            && $this->paymentMethods['billie']->getProperty('enabled') === 'yes';
        if ($isBillieEnabled) {
            $content .= '<div class="notice notice-warning is-dismissible">';
            $content .= '<p>';
            $content .= __(
                'You have activated Billie. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. The billing company field is required as well. Make sure to enable the billing company field in the WooCommerce settings if you are using Woocommerce blocks.',
                'mollie-payments-for-woocommerce'
            );
            $content .= '</p>';
            $content .= '</div>';
        }
        return $content;
    }
}
