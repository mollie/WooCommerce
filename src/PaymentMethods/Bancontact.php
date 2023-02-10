<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Bancontact extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'bancontact',
            'defaultTitle' => __('Bancontact', 'mollie-payments-for-woocommerce'),
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
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
