<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Payconiq extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'payconiq',
            'defaultTitle' => 'payconiq',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => ['products', 'refunds'],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'docs' => '',
        ];
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('payconiq', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
