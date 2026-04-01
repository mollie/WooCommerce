<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Trustly extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @return array<mixed>
     */
    protected function getConfig(): array
    {
        return [
            'id' => 'trustly',
            'defaultTitle' => 'Trustly',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => true,
            'SEPA' => true,
            'docs' => 'https://www.mollie.com/gb/payments/trustly',
        ];
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Trustly', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    /**
     * @param array<mixed> $generalFormFields
     * @return array<mixed>
     */
    public function getFormFields(array $generalFormFields): array
    {
        return $generalFormFields;
    }
}
