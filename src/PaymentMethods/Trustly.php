<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Trustly extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected function getConfig(): array
    {
        return ['id' => 'trustly', 'defaultTitle' => 'Trustly', 'settingsDescription' => '', 'defaultDescription' => '', 'paymentFields' => \false, 'instructions' => \true, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \true, 'SEPA' => \true, 'docs' => 'https://www.mollie.com/gb/payments/trustly'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Trustly', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
