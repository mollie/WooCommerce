<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Bancomatpay extends AbstractPaymentMethod implements PaymentMethodI
{
    public function getConfig(): array
    {
        return [
            'id' => 'bancomatpay',
            'defaultTitle' => __('Bancomat Pay', 'mollie-payments-for-woocommerce'),
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
            'docs' => 'https://www.mollie.com/gb/payments/bancomat-pay',
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
