<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Mobilepay extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    public function getConfig(): array
    {
        return ['id' => 'mobilepay', 'defaultTitle' => 'Mobilepay', 'settingsDescription' => '', 'defaultDescription' => '', 'paymentFields' => \true, 'additionalFields' => ['phone'], 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'docs' => 'https://www.mollie.com/gb/payments/mobilepay'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Mobilepay', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
