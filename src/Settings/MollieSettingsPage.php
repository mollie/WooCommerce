<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use Mollie\WooCommerce\Settings\Page\AbstractPage;
use Mollie\WooCommerce\Settings\Page\PageAdvancedSettings;
use Mollie\WooCommerce\Settings\Page\PageApiKeys;
use Mollie\WooCommerce\Settings\Page\PageNoApiKey;
use Mollie\WooCommerce\Settings\Page\PagePaymentMethods;
use Mollie\WooCommerce\Shared\Data;
use WC_Admin_Settings;
use WC_Settings_Page;

class MollieSettingsPage extends WC_Settings_Page
{
    protected Settings $settings;
    protected string $pluginPath;
    protected string $pluginUrl;
    protected array $mollieGateways;
    protected array $paymentMethods;
    protected bool $isTestModeEnabled;
    protected Data $dataHelper;

    public function __construct(
        Settings $settings,
        string $pluginPath,
        string $pluginUrl,
        array $mollieGateways,
        array $paymentMethods,
        bool $isTestModeEnabled,
        Data $dataHelper
    ) {

        $this->id = 'mollie_settings';
        $this->label = __('Mollie Settings', 'mollie-payments-for-woocommerce');
        $this->settings = $settings;
        $this->pluginPath = $pluginPath;
        $this->pluginUrl = $pluginUrl;
        $this->mollieGateways = $mollieGateways;
        $this->isTestModeEnabled = $isTestModeEnabled;
        $this->dataHelper = $dataHelper;
        $this->paymentMethods = $paymentMethods;
        $this->registerContentFieldType();
        $this->outputSections();
        parent::__construct();
    }

    public function registerContentFieldType(): void
    {
        add_action('woocommerce_admin_field_mollie_custom_input', static function ($value): void {
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php
                    echo esc_attr($value['id']); ?>"><?php
                        echo esc_html($value['title']);  ?></label>
                </th>
                <td class="forminp">
                    <?php
                    if (!empty($value['value'])) : ?>
                        <?= $value['value']; // WPCS: XSS ok. ?>
                        <?php
                    endif; ?>

                    <?php
                    if (!empty($value['desc'])) : ?>
                        <p class="description"><?= $value['desc']; // WPCS: XSS ok. ?></p>
                        <?php
                    endif; ?>
                </td>
            </tr>
            <?php
        });

        add_action('woocommerce_admin_field_mollie_content', static function ($value): void {
            if (!empty($value['value'])) {
                echo $value['value']; // WPCS: XSS ok.
            }
        });
    }

    public function outputSections()
    {
        add_action(
            'woocommerce_sections_' . $this->id,
            [$this, 'output_sections']
        );
    }

    protected function pages(): array
    {
        return [
                PageNoApiKey::class,
                PageApiKeys::class,
                PagePaymentMethods::class,
                PageAdvancedSettings::class,
        ];
    }

    public function get_settings($currentSection = '')
    {
        $defaultSection = $currentSection;
        $connectionStatus = $this->settings->getConnectionStatus();

        if (!$connectionStatus) {
            $defaultSection = PageNoApiKey::slug();
        }

        if ($connectionStatus && $defaultSection === PageNoApiKey::slug()) {
            $defaultSection = PageApiKeys::slug();
        }

        if ($defaultSection === '') {
            $defaultSection = PageApiKeys::slug();
        }

        $mollieSettings = null;
        foreach ($this->pages() as $pageClass) {
            /** @var AbstractPage $page */
            $page = new $pageClass(
                $this->settings,
                $this->pluginUrl,
                $this->pages(),
                $defaultSection,
                $connectionStatus,
                $this->isTestModeEnabled,
                $this->mollieGateways,
                $this->paymentMethods,
                $this->dataHelper
            );
            if ($page::slug() === $defaultSection) {
                $mollieSettings = $this->hideKeysIntoStars($page->settings());
                break;
            }
        }

        return apply_filters(
            'woocommerce_get_settings_' . $this->id,
            $mollieSettings,
            $currentSection
        );
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
        $settings = $this->saveApiKeys($settings);
        WC_Admin_Settings::save_fields($settings);
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
}
