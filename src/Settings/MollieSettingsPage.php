<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use Mollie\WooCommerce\Settings\Page\AbstractPage;
use Mollie\WooCommerce\Settings\Page\PageAdvancedSettings;
use Mollie\WooCommerce\Settings\Page\PageApiKeys;
use Mollie\WooCommerce\Settings\Page\PageNoApiKey;
use Mollie\WooCommerce\Settings\Page\PagePaymentMethods;
use Mollie\WooCommerce\Shared\Data;
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
                $mollieSettings = $page->settings();
                break;
            }
        }

        return apply_filters(
            'woocommerce_get_settings_' . $this->id,
            $mollieSettings,
            $currentSection
        );
    }
}
