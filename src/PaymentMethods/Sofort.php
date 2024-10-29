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
            'docs' => 'https://help.mollie.com/hc/en-us/articles/20904206772626-SOFORT-Deprecation-30-September-2024',
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
