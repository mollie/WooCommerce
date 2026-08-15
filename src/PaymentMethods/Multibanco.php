<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Multibanco extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected function getConfig(): array
    {
        return ['id' => 'multibanco', 'defaultTitle' => 'Multibanco', 'settingsDescription' => 'To accept payments via Multibanco', 'defaultDescription' => '', 'paymentFields' => \false, 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \true, 'docs' => ''];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Multibanco', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __('To accept payments via Multibanco', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
