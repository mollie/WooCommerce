<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Twint extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'twint',
            'defaultTitle' => __('Twint', 'mollie-payments-for-woocommerce'),
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
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
