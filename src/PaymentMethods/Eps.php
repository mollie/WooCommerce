<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Eps extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'eps',
            'defaultTitle' => 'EPS',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => true,
            'SEPA' => true,
            'docs' => 'https://www.mollie.com/gb/payments/eps',
        ];
    }

    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('EPS', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
