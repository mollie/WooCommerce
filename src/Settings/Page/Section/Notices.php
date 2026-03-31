<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod;
use Mollie\WooCommerce\PaymentMethods\Constants;
use WC_Gateway_BACS;
class Notices extends \Mollie\WooCommerce\Settings\Page\Section\AbstractSection
{
    /**
     * @var mixed
     */
    private $mollieGateways;
    /**
     * @var mixed
     */
    private $paymentMethods;
    public function config(): array
    {
        return [['id' => $this->settings->getSettingId('notices'), 'type' => 'mollie_content', 'value' => $this->content()]];
    }
    protected function content(): string
    {
        $this->mollieGateways = $this->container->get('__deprecated.gateway_helpers');
        $this->paymentMethods = $this->container->get('gateway.paymentMethods');
        ob_start();
        ?>
        <div class="mollie-section mollie-section--notices">
            <?php 
        echo $this->warnAboutRequiredCheckoutFieldForBillie();
        // WPCS: XSS ok.     
        ?>
            <?php 
        echo $this->warnAboutRequiredCheckoutFieldForKlarna();
        // WPCS: XSS ok.     
        ?>
            <?php 
        echo $this->warnMollieBankTransferNotBACS();
        // WPCS: XSS ok.     
        ?>
            <?php 
        echo $this->warnDirectDebitStatus();
        // WPCS: XSS ok.     
        ?>
        </div>
        <?php 
        return ob_get_clean();
    }
    protected function warnDirectDebitStatus(): string
    {
        if (!isset($this->paymentMethods[Constants::DIRECTDEBIT]) || !$this->paymentMethods[Constants::DIRECTDEBIT] instanceof AbstractPaymentMethod) {
            return '';
        }
        $hasCustomSepaSettings = $this->paymentMethods[Constants::DIRECTDEBIT]->getProperty('enabled') !== \false;
        $isSepaEnabled = !$hasCustomSepaSettings || $this->paymentMethods[Constants::DIRECTDEBIT]->getProperty('enabled') === 'yes';
        $sepaGatewayAllowed = !empty($this->mollieGateways['mollie_wc_gateway_' . Constants::DIRECTDEBIT]);
        if (!($sepaGatewayAllowed && !$isSepaEnabled)) {
            return '';
        }
        return $this->notice(__("You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL and/or other 'first' payment methods.", 'mollie-payments-for-woocommerce'));
    }
    protected function warnAboutRequiredCheckoutFieldForBillie(): string
    {
        $isBillieEnabled = array_key_exists('mollie_wc_gateway_billie', $this->mollieGateways) && array_key_exists('billie', $this->paymentMethods) && $this->paymentMethods['billie']->getProperty('enabled') === 'yes';
        if (!$isBillieEnabled) {
            return '';
        }
        return $this->notice(__('You have activated Billie. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. The billing company field is required as well. Make sure to enable the billing company field in the WooCommerce settings if you are using WooCommerce blocks.', 'mollie-payments-for-woocommerce'));
    }
    protected function warnAboutRequiredCheckoutFieldForKlarna(): string
    {
        if (!$this->isKlarnaEnabled()) {
            return '';
        }
        return $this->notice(sprintf(
            /* translators: Placeholder 1: Opening link tag. Placeholder 2: Closing link tag. */
            __('You have activated Klarna. To accept payments, please make sure all default WooCommerce checkout fields are enabled and required. For more information, visit the %1$sKlarna documentation%2$s.', 'mollie-payments-for-woocommerce'),
            '<a href="https://github.com/mollie/WooCommerce/wiki/Setting-up-Klarna-gateway">',
            '</a>'
        ));
    }
    protected function warnMollieBankTransferNotBACS(): string
    {
        $woocommerceBanktransferGateway = new WC_Gateway_BACS();
        if (!$woocommerceBanktransferGateway->is_available()) {
            return '';
        }
        return $this->notice(__('You have the WooCommerce default Direct Bank Transfer (BACS) payment gateway enabled in WooCommerce. Mollie strongly advises only using Bank Transfer via Mollie and disabling the default WooCommerce BACS payment gateway to prevent possible conflicts.', 'mollie-payments-for-woocommerce'));
    }
    protected function isKlarnaEnabled(): bool
    {
        $klarnaGateways = [Constants::KLARNAPAYLATER, Constants::KLARNASLICEIT, Constants::KLARNAPAYNOW, Constants::KLARNA];
        $isKlarnaEnabled = \false;
        foreach ($klarnaGateways as $klarnaGateway) {
            if (array_key_exists('mollie_wc_gateway_' . $klarnaGateway, $this->mollieGateways) && array_key_exists($klarnaGateway, $this->paymentMethods) && $this->paymentMethods[$klarnaGateway]->getProperty('enabled') === 'yes') {
                $isKlarnaEnabled = \true;
                break;
            }
        }
        return $isKlarnaEnabled;
    }
    protected function notice(string $message)
    {
        //notice-warning is-dismissible
        ob_start();
        ?>
        <div class="mollie-notice">
            <p>
                <?php 
        echo wp_kses($message, ['a' => ['href' => [], 'target' => []]]);
        ?>
            </p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>
        <?php 
        return ob_get_clean();
    }
    public function styles(): string
    {
        ob_start();
        ?>
        <style>
            .mollie-notice {
                margin: 15px 0;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-left-width: 4px;
                border-left-color: #dba617;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
                padding: 12px 12px;
                box-sizing: border-box;
                position: relative;
            }

            .mollie-notice p {
                margin: 0;
                padding-right: 40px;
            }

            .mollie-notice button::before {
                background: 0 0;
                color: #787c82;
                content: "\f153";
                display: block;
                font: normal 16px / 20px dashicons;
                speak: never;
                height: 20px;
                text-align: center;
                width: 20px;
                -webkit-font-smoothing: antialiased;
            }
        </style>
        <?php 
        return ob_get_clean();
    }
}
