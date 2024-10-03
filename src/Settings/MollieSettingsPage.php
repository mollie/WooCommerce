<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use Mollie\WooCommerce\Settings\Page\AbstractPage;
use Mollie\WooCommerce\Settings\Page\PageAdvancedSettings;
use Mollie\WooCommerce\Settings\Page\PageApiKeys;
use Mollie\WooCommerce\Settings\Page\PageNoApiKey;
use Mollie\WooCommerce\Settings\Page\PagePaymentMethods;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\PaymentMethods\Constants;
use WC_Gateway_BACS;
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
}
