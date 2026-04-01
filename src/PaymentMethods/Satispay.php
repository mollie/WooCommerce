<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Satispay extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @return array<mixed>
     */
    protected function getConfig(): array
    {
        return [
            'id' => 'satispay',
            'defaultTitle' => 'Satispay',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => ['products', 'refunds'],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'docs' => 'https://www.mollie.com/gb/payments/satispay',
        ];
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Satispay', 'mollie-payments-for-woocommerce');
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
