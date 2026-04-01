<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Alma extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @return array<mixed>
     */
    protected function getConfig(): array
    {
        return [
            'id' => 'alma',
            'defaultTitle' => 'Alma',
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
            'paymentAPIfields' => [
                'AddressMiddleware',
            ],
            'docs' => 'https://www.mollie.com/gb/payments/alma',
        ];
    }

    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Alma', 'mollie-payments-for-woocommerce');
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
