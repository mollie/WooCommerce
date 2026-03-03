<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
class Riverty extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected function getConfig(): array
    {
        return ['id' => 'riverty', 'defaultTitle' => 'Riverty', 'settingsDescription' => 'To accept payments via Riverty, all default WooCommerce checkout fields should be enabled and required.', 'defaultDescription' => '', 'paymentFields' => \true, 'additionalFields' => ['birthdate', 'phone'], 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'paymentCaptureMode' => 'manual', 'phonePlaceholder' => 'Please enter your phone here. +316xxxxxxxx', 'mollie-payments-for-woocommerce', 'birthdatePlaceholder' => 'Please enter your birthdate here.', 'mollie-payments-for-woocommerce', 'docs' => 'https://www.mollie.com/gb/payments/riverty'];
    }
    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Riverty', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __('To accept payments via Riverty, all default WooCommerce checkout fields should be enabled and required.', 'mollie-payments-for-woocommerce');
        $this->config['phonePlaceholder'] = __('Please enter your phone here. +316xxxxxxxx', 'mollie-payments-for-woocommerce');
        $this->config['birthdatePlaceholder'] = __('Please enter your birthdate here.', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
