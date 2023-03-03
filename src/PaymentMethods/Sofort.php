<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Sofort extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'sofort',
            'defaultTitle' => __('SOFORT Banking', 'mollie-payments-for-woocommerce'),
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
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
