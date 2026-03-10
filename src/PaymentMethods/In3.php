<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class In3 extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    public function getConfig(): array
    {
        return ['id' => 'in3', 'defaultTitle' => 'in3', 'settingsDescription' => '', 'defaultDescription' => 'Pay in 3 instalments, 0% interest', 'paymentFields' => \true, 'additionalFields' => ['birthdate', 'phone'], 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'errorMessage' => 'Required field is empty or invalid. Phone (+316xxxxxxxx) and birthdate fields are required.', 'phonePlaceholder' => 'Please enter your phone here. +316xxxxxxxx', 'birthdatePlaceholder' => 'Please enter your birthdate here.', 'docs' => 'https://www.mollie.com/gb/payments/ideal-in3'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('in3', 'mollie-payments-for-woocommerce');
        $this->config['defaultDescription'] = __('Pay in 3 instalments, 0% interest', 'mollie-payments-for-woocommerce');
        $this->config['errorMessage'] = __('Required field is empty or invalid. Phone (+316xxxxxxxx) and birthdate fields are required.', 'mollie-payments-for-woocommerce');
        $this->config['phonePlaceholder'] = __('Please enter your phone here. +316xxxxxxxx', 'mollie-payments-for-woocommerce');
        $this->config['birthdatePlaceholder'] = __('Please enter your birthdate here.', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
