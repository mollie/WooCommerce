<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Mybank extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected function getConfig(): array
    {
        return ['id' => 'mybank', 'defaultTitle' => 'MyBank', 'settingsDescription' => 'To accept payments via MyBank', 'defaultDescription' => '', 'paymentFields' => \false, 'instructions' => \true, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'docs' => ''];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('MyBank', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __('To accept payments via MyBank', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
