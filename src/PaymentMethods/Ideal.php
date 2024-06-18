<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Ideal extends AbstractPaymentMethod implements PaymentMethodI
{
    protected const DEFAULT_ISSUERS_DROPDOWN = 'yes';

    public function getConfig(): array
    {
        return [
            'id' => 'ideal',
            'defaultTitle' => __('iDEAL', 'mollie-payments-for-woocommerce'),
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
