<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class In3 extends AbstractPaymentMethod implements PaymentMethodI
{
    public function getConfig(): array
    {
        return [
            'id' => 'in3',
            'defaultTitle' => __('in3', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Pay in 3 instalments, 0% interest', 'mollie-payments-for-woocommerce'),
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'orderMandatory' => true,
            'errorMessage' => __(
                'Required field is empty. To proceed with In3 payment, phone and birthdate fields are required.',
                'mollie-payments-for-woocommerce'
            ),
            'phonePlaceholder' => __('To proceed with In3, please enter your phone here. +00 00000000', 'mollie-payments-for-woocommerce'),
            'birthdatePlaceholder' => __('To proceed with In3, please enter your birthdate here.', 'mollie-payments-for-woocommerce'),
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
