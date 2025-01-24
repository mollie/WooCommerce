<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Twint extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'twint',
            'defaultTitle' => 'Twint',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false,
            'docs' => 'https://www.mollie.com/gb/payments/twint',
        ];
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Twint', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
