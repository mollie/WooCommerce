<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod;
use Mollie\WooCommerce\PaymentMethods\Constants;
class PaymentMethods extends \Mollie\WooCommerce\Settings\Page\Section\AbstractSection
{
    public function config(): array
    {
        return [['id' => $this->settings->getSettingId('notices'), 'type' => 'mollie_content', 'value' => $this->content()]];
    }
    protected function content(): string
    {
        ob_start();
        ?>
        <div class="mollie-section mollie-section--pm">
            <div class="mollie-settings-pm">
                <?php 
        echo $this->renderGateways();
        // WPCS: XSS ok. 
        ?>
            </div>
        </div>
        <?php 
        return ob_get_clean();
    }
    public function renderGateways(): string
    {
        $titleActivePaymentMethods = __('Currently Active Payment Methods', 'mollie-payments-for-woocommerce');
        $descriptionActivePaymentMethods = __('These payment methods are active in your Mollie profile.
        You can enable these payment methods in their settings to make them available for your customers.', 'mollie-payments-for-woocommerce');
        $titleInactivePaymentMethods = __('Inactive Payment Methods', 'mollie-payments-for-woocommerce');
        $descriptionInactivePaymentMethods = __('These payment methods are available in your Mollie profile but are
        not currently active. Activate them to offer more payment options to your customers.', 'mollie-payments-for-woocommerce');
        $activatedGateways = '';
        $deactivatedGateways = '';
        /** @var AbstractPaymentMethod $paymentMethod */
        $paymentMethods = $this->container->get('gateway.paymentMethods');
        $enabledMethods = $this->container->get('gateway.paymentMethodsEnabledAtMollie');
        if (!in_array(Constants::DIRECTDEBIT, $enabledMethods, \true)) {
            unset($paymentMethods[Constants::DIRECTDEBIT]);
        }
        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodId = $paymentMethod->getProperty('id');
            $enabledInMollie = in_array($paymentMethodId, $enabledMethods, \true);
            //don't display old klarna GWs
            if (isset($paymentMethods[Constants::KLARNA]) && in_array($paymentMethodId, [Constants::KLARNASLICEIT, Constants::KLARNAPAYLATER, Constants::KLARNAPAYNOW], \true)) {
                if (!$enabledInMollie || $paymentMethod->getProperty('enabled') !== 'yes') {
                    continue;
                }
            }
            $paymentGatewayButton = $this->paymentGatewayButton($paymentMethod, $enabledInMollie);
            if ($enabledInMollie) {
                $activatedGateways .= $paymentGatewayButton;
            } else {
                $deactivatedGateways .= $paymentGatewayButton;
            }
        }
        return $this->paymentGatewaysBlock($titleActivePaymentMethods, $descriptionActivePaymentMethods, $activatedGateways) . $this->paymentGatewaysBlock($titleInactivePaymentMethods, $descriptionInactivePaymentMethods, $deactivatedGateways);
    }
    protected function paymentGatewaysBlock(string $title, string $description, string $html): string
    {
        ob_start();
        ?>
        <div class="mollie-settings-pm__wrap">
            <h3><?php 
        echo esc_html($title);
        ?></h3>
            <p><?php 
        echo esc_html($description);
        ?></p>
            <div class="mollie-settings-pm__list">
                <?php 
        echo $html;
        // phpcs:ignore XSS ok. 
        ?>
            </div>
        </div>
        <?php 
        return ob_get_clean();
    }
    protected function getGatewaySettingsUrl(string $gatewayClassName): string
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($gatewayClassName)));
    }
    public function styles(): string
    {
        ob_start();
        ?>
        <style>
            .mollie-section--pm {
                margin-top: 40px;
            }

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

            @media screen and (max-width: 1100px) {
                .mollie-settings-pm__single {
                    width: 100%;
                }
            }

        </style>

        <?php 
        return ob_get_clean();
    }
    protected function paymentGatewayButton(AbstractPaymentMethod $paymentMethod, $enabledInMollie): string
    {
        $documentationLink = $paymentMethod->getProperty('docs');
        $paymentMethodId = $paymentMethod->getProperty('id');
        $gatewayKey = 'mollie_wc_gateway_' . $paymentMethodId;
        $button = '<a class="button-secondary" href="' . $this->getGatewaySettingsUrl($gatewayKey) . '">' . esc_html(__('Manage Payment Method', 'mollie-payments-for-woocommerce')) . '</a>';
        $messageOrLink = '';
        $enabledInWoo = $paymentMethod->getSettings()['enabled'] === 'yes';
        if ($enabledInMollie && $enabledInWoo) {
            $messageOrLink = '<span class="mollie-settings-pm__status mollie-settings-pm__status--enabled">' . esc_html(__('enabled', 'mollie-payments-for-woocommerce')) . '</span>';
        } elseif ($enabledInMollie && !$enabledInWoo) {
            $messageOrLink = '<span class="mollie-settings-pm__status mollie-settings-pm__status--disabled">' . esc_html(__('disabled', 'mollie-payments-for-woocommerce')) . '</span>';
        } else {
            if ($documentationLink) {
                $messageOrLink = "<a class='mollie-settings-pm__info' href='" . $documentationLink . "' target='_blank'>" . esc_html(__('More information', 'mollie-payments-for-woocommerce')) . '</a>';
            }
            $button = '<a class="button-secondary" href="https://my.mollie.com/dashboard/settings/profiles?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">' . esc_html(__('Activate Payment Method', 'mollie-payments-for-woocommerce')) . '</a>';
        }
        $iconProvider = $paymentMethod->paymentMethodIconProvider($this->container);
        $icon = $iconProvider->provideIcons()[0];
        ob_start();
        ?>
        <div class="mollie-settings-pm__single">
            <img src="<?php 
        echo esc_url($icon->src());
        ?>" class="mollie-gateway-icon" alt="<?php 
        echo esc_attr($paymentMethod->title($this->container));
        ?>"/>
            <?php 
        echo esc_html($paymentMethod->title($this->container));
        ?>
            <?php 
        echo $messageOrLink;
        // phpcs:ignore XSS ok.
        ?>
            <?php 
        echo $button;
        // phpcs:ignore XSS ok.
        ?>
        </div>
        <?php 
        return ob_get_clean();
    }
}
