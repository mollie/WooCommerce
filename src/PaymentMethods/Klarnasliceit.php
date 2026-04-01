<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Klarnasliceit extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @return array<mixed>
     */
    protected function getConfig(): array
    {
        return [
            'id' => 'klarnasliceit',
            'defaultTitle' => 'Klarna Slice it',
            'settingsDescription' => 'To accept payments via Klarna, all default WooCommerce checkout fields should be enabled and required.',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'docs' => 'https://www.mollie.com/gb/payments/klarna',
        ];
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Klarna Slice it', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __(
            'To accept payments via Klarna, all default WooCommerce checkout fields should be enabled and required.',
            'mollie-payments-for-woocommerce'
        );
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
