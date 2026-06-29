<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Wero extends AbstractPaymentMethod implements PaymentMethodI
{
    public function getConfig(): array
    {
        return [
            'id' => 'wero',
            'defaultTitle' => 'Wero',
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
            'docs' => 'https://www.mollie.com/gb/payments/wero',
        ];
    }

    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Wero', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}