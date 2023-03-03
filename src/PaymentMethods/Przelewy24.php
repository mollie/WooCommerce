<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Przelewy24 extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'przelewy24',
            'defaultTitle' => __('Przelewy24', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __(
                'To accept payments via Przelewy24, a customer email is required for every payment.',
                'mollie-payments-for-woocommerce'
            ),
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
