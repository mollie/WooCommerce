<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod;

class PaymentMethods extends AbstractSection
{
    public function config(): array
    {
        return [
                [
                        'id' => $this->settings->getSettingId('notices'),
                        'type' => 'mollie_content',
                        'value' => $this->content(),
                ],
        ];
    }

    protected function content(): string
    {
        ob_start();
        ?>
        <div class="mollie-section">
            <div class="mollie-settings-pm">
                <?= $this->renderGateways(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function renderGateways(): string
    {
        $this->refreshIfRequested();
        $this->cleanDbIfRequested();

        $messageEnabled = '<span class="mollie-settings-pm__status mollie-settings-pm__status--enabled">' . __(
            'enabled',
            'mollie-payments-for-woocommerce'
        ) . '</span>';

        $messageDisabled = '<span class="mollie-settings-pm__status mollie-settings-pm__status--disabled">' . __(
            'disabled',
            'mollie-payments-for-woocommerce'
        ) . '</span>';

        $titleActivePaymentMethods = __(
            'Currently Active Payment Methods',
            'mollie-payments-for-woocommerce'
        );
        $descriptionActivePaymentMethods = __(
            'These payment methods are active in your Mollie profile. 
        You can enable these payment methods in their settings to make them available for your customers.',
            'mollie-payments-for-woocommerce'
        );
        $titleInactivePaymentMethods = __('Inactive Payment Methods', 'mollie-payments-for-woocommerce');
        $descriptionInactivePaymentMethods = __(
            'These payment methods are available in your Mollie profile but are 
        not currently active. Activate them to offer more payment options to your customers.',
            'mollie-payments-for-woocommerce'
        );

        $activatedGateways = '';
        $deactivatedGateways = '';

        /** @var AbstractPaymentMethod $paymentMethod */
        foreach ($this->paymentMethods as $paymentMethod) {
            $paymentMethodId = $paymentMethod->getProperty('id');
            $gatewayKey = 'mollie_wc_gateway_' . $paymentMethodId;
            $enabledAtMollie = array_key_exists($gatewayKey, $this->mollieGateways);
            $enabledInWoo = ($paymentMethod->getSettings())['enabled'] === 'yes';
            $paymentGatewayButton = '<div class="mollie-settings-pm__single">';
            $paymentGatewayButton .= $paymentMethod->getIconUrl();
            $paymentGatewayButton .= $paymentMethod->title();
            $documentationLink = $paymentMethod->getProperty('docs');
            $moreInformation = '';

            if ($documentationLink) {
                $moreInformation = "<a class='mollie-settings-pm__info' href='" . $documentationLink . "'>" . __(
                    'More information',
                    'mollie-payments-for-woocommerce'
                ) . '</a>';
            }

            if ($enabledAtMollie) {
                if ($enabledInWoo) {
                    $paymentGatewayButton .= $messageEnabled;
                } else {
                    $paymentGatewayButton .= $messageDisabled;
                }
                $paymentGatewayButton .= '<a class="button-secondary" href="' . $this->getGatewaySettingsUrl(
                    $gatewayKey
                ) . '">' . __(
                    'Manage Payment Method',
                    'mollie-payments-for-woocommerce'
                ) . '</a>';
            } else {
                $paymentGatewayButton .= $moreInformation;
                $paymentGatewayButton .= ' <a class="button-secondary" href="https://my.mollie.com/dashboard/settings/profiles?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">' .
                        __('Activate Payment Method', 'mollie-payments-for-woocommerce')
                        . '</a>';
            }

            $paymentGatewayButton .= '</div>';
            if ($enabledAtMollie) {
                $activatedGateways .= $paymentGatewayButton;
            } else {
                $deactivatedGateways .= $paymentGatewayButton;
            }
        }

        return $this->paymentGatewaysBlock(
            $titleActivePaymentMethods,
            $descriptionActivePaymentMethods,
            $activatedGateways
        ) . $this->paymentGatewaysBlock(
            $titleInactivePaymentMethods,
            $descriptionInactivePaymentMethods,
            $deactivatedGateways
        );
    }

    protected function paymentGatewaysBlock(string $title, string $description, string $html): string
    {
        ob_start();
        ?>
        <div class="mollie-settings-pm__wrap">
            <h3><?= $title; ?></h3>
            <p><?= $description; ?></p>
            <div class="mollie-settings-pm__list">
                <?= $html; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function getGatewaySettingsUrl(string $gatewayClassName): string
    {
        return admin_url(
            'admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($gatewayClassName))
        );
    }

    public function styles(): string
    {
        ob_start();
        ?>
        <style>
            .mollie-settings-pm {
                background-color: #fff;
                padding: 16px;
                border-radius: 12px;
                border: 1px solid #c3c4c7;
                display: flex;
                flex-direction: column;
                gap: 24px;
            }

            .mollie-settings-pm__list {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                row-gap: 12px;
            }

            .mollie-settings-pm__single {
                background-color: #f0f0f1;
                width: calc(50% - 6px);
                display: flex;
                padding: 8px;
                box-sizing: border-box;
                align-items: center;
                gap: 8px;
                border-radius: 4px;
                border: 1px solid #c3c4c7;
            }

            .mollie-settings-pm__single .button-secondary {
                margin-left: auto;
            }

            .mollie-settings-pm__status--enabled {
                color: green;
            }

            .mollie-settings-pm__status--disabled {
                color: red;
            }

            .mollie-settings-pm__info {
                color: #646970;
                text-decoration: none;
                text-wrap: nowrap;
                padding-right: 16px;
            }

        </style>

        <?php
        return ob_get_clean();
    }

    protected function refreshIfRequested()
    {
        if (
                isset($_GET['refresh-methods']) &&
                isset($_GET['nonce_mollie_refresh_methods']) &&
                wp_verify_nonce(
                    filter_input(INPUT_GET, 'nonce_mollie_refresh_methods', FILTER_SANITIZE_SPECIAL_CHARS),
                    'nonce_mollie_refresh_methods'
                )
        ) {
            $testMode = $this->testModeEnabled;
            $apiKey = $this->settings->getApiKey();
            /* Reload active Mollie methods */
            $methods = $this->dataHelper->getAllPaymentMethods($apiKey, $testMode, false);
            foreach ($methods as $key => $method) {
                $methods['mollie_wc_gateway_' . $method['id']] = $method;
                unset($methods[$key]);
            }
            $this->mollieGateways = $methods;
        }
    }

    protected function cleanDbIfRequested()
    {

        if (
                isset($_GET['cleanDB-mollie']) && wp_verify_nonce(
                    filter_input(INPUT_GET, 'nonce_mollie_cleanDb', FILTER_SANITIZE_SPECIAL_CHARS),
                    'nonce_mollie_cleanDb'
                )
        ) {
            $cleaner = $this->settings->cleanDb();
            $cleaner->cleanAll();
            //set default settings
            foreach ($this->paymentMethods as $paymentMethod) {
                $paymentMethod->getSettings();
            }
        }
    }
}
