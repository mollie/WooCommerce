<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Przelewy24 extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected function getConfig(): array
    {
        return ['id' => 'przelewy24', 'defaultTitle' => 'Przelewy24', 'settingsDescription' => 'To accept payments via Przelewy24, a customer email is required for every payment.', 'defaultDescription' => '', 'paymentFields' => \false, 'instructions' => \true, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'docs' => 'https://www.mollie.com/gb/payments/przelewy24'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Przelewy24', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __('To accept payments via Przelewy24, a customer email is required for every payment.', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
