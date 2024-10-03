<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\PaymentMethods\Constants;
use WC_Gateway_BACS;

class Notices extends AbstractSection
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
        <div class="mollie-section mollie-section--notices">
            <?= $this->warnAboutRequiredCheckoutFieldForBillie(); // WPCS: XSS ok.?>
            <?= $this->warnAboutRequiredCheckoutFieldForKlarna(); // WPCS: XSS ok.?>
            <?= $this->warnMollieBankTransferNotBACS(); // WPCS: XSS ok.?>
            <?= $this->warnDirectDebitStatus(); // WPCS: XSS ok.?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function warnDirectDebitStatus(): string
    {
        $hasCustomSepaSettings = $this->paymentMethods["directdebit"]->getProperty('enabled') !== false;
        $isSepaEnabled = !$hasCustomSepaSettings || $this->paymentMethods["directdebit"]->getProperty(
            'enabled'
        ) === 'yes';
        $sepaGatewayAllowed = !empty($this->mollieGateways["mollie_wc_gateway_directdebit"]);

        if (!($sepaGatewayAllowed && !$isSepaEnabled)) {
            return '';
        }
        return $this->notice(
            __(
                "You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL and/or other 'first' payment methods.",
                'mollie-payments-for-woocommerce'
            )
        );
    }

    protected function warnAboutRequiredCheckoutFieldForBillie(): string
    {
        $isBillieEnabled = array_key_exists('mollie_wc_gateway_billie', $this->mollieGateways)
                && array_key_exists('billie', $this->paymentMethods)
                && $this->paymentMethods['billie']->getProperty('enabled') === 'yes';

        if (!$isBillieEnabled) {
            return '';
        }
        return $this->notice(
            __(
                'You have activated Billie. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. The billing company field is required as well. Make sure to enable the billing company field in the WooCommerce settings if you are using Woocommerce blocks.',
                'mollie-payments-for-woocommerce'
            )
        );
    }

    protected function warnAboutRequiredCheckoutFieldForKlarna(): string
    {
        if (!$this->isKlarnaEnabled()) {
            return '';
        }
        return $this->notice(
            sprintf(
                /* translators: Placeholder 1: Opening link tag. Placeholder 2: Closing link tag. Placeholder 3: Opening link tag. Placeholder 4: Closing link tag. */
                __(
                    'You have activated Klarna. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. For more information, go to %1$sKlarna Pay Later documentation%2$s or  %3$sKlarna Slice it documentation%4$s',
                    'mollie-payments-for-woocommerce'
                ),
                '<a href="https://github.com/mollie/WooCommerce/wiki/Setting-up-Klarna-Pay-later-gateway">',
                '</a>',
                '<a href=" https://github.com/mollie/WooCommerce/wiki/Setting-up-Klarna-Slice-it-gateway">',
                '</a>'
            )
        );
    }

    protected function warnMollieBankTransferNotBACS(): string
    {
        $woocommerceBanktransferGateway = new WC_Gateway_BACS();
        if (!$woocommerceBanktransferGateway->is_available()) {
            return '';
        }

        return $this->notice(
            __(
                'You have the WooCommerce default Direct Bank Transfer (BACS) payment gateway enabled in WooCommerce. Mollie strongly advices only using Bank Transfer via Mollie and disabling the default WooCommerce BACS payment gateway to prevent possible conflicts.',
                'mollie-payments-for-woocommerce'
            )
        );
    }

    protected function isKlarnaEnabled(): bool
    {
        $klarnaGateways = [
                Constants::KLARNAPAYLATER,
                Constants::KLARNASLICEIT,
                Constants::KLARNAPAYNOW,
                Constants::KLARNA,
        ];
        $isKlarnaEnabled = false;
        foreach ($klarnaGateways as $klarnaGateway) {
            if (
                    array_key_exists('mollie_wc_gateway_' . $klarnaGateway, $this->mollieGateways)
                    && array_key_exists($klarnaGateway, $this->paymentMethods)
                    && $this->paymentMethods[$klarnaGateway]->getProperty('enabled') === 'yes'
            ) {
                $isKlarnaEnabled = true;
                break;
            }
        }
        return $isKlarnaEnabled;
    }

    protected function notice(string $message)
    {
        ob_start();
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?= wp_kses($message, [
                        'a' => [
                                'href' => [],
                                'target' => [],
                        ],
                ]); ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}
