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
            'paymentFields' => true,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'errorMessage' => __(
                'Required field is empty. Phone field is required.',
                'mollie-payments-for-woocommerce'
            ),
            'phonePlaceholder' => __('Please enter your phone here. +00..', 'mollie-payments-for-woocommerce'),
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
