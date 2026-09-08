<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Bizum extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected function getConfig(): array
    {
        return ['id' => 'bizum', 'defaultTitle' => 'Bizum', 'settingsDescription' => 'To accept payments via Bizum, all default WooCommerce checkout fields should be enabled and required.', 'defaultDescription' => '', 'paymentFields' => \true, 'additionalFields' => ['phone'], 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'phonePlaceholder' => 'Please enter your phone here. +346xxxxxxxx', 'mollie-payments-for-woocommerce', 'docs' => 'https://www.mollie.com/gb/payments/bizum'];
    }
    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Bizum', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __('To accept payments via Bizum, all default WooCommerce checkout fields should be enabled and required.', 'mollie-payments-for-woocommerce');
        $this->config['phonePlaceholder'] = __('Please enter your phone here. +346xxxxxxxx', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
