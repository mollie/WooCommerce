<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Vipps extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    public function getConfig(): array
    {
        return ['id' => 'vipps', 'defaultTitle' => 'Vipps', 'settingsDescription' => '', 'defaultDescription' => '', 'paymentFields' => \true, 'additionalFields' => ['phone'], 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'docs' => 'https://www.mollie.com/gb/payments/vipps'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Vipps', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
