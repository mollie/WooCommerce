<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Billie extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'billie',
            'defaultTitle' => __('Billie', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __(
                'To accept payments via Billie, all default WooCommerce checkout fields should be enabled and required.',
                'mollie-payments-for-woocommerce'
            ),
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
            'orderMandatory' => true,
            'errorMessage' => __(
                'Company field is empty. The company field is required.',
                'mollie-payments-for-woocommerce'
            ),
            'companyPlaceholder' => __('Please enter your company name here.', 'mollie-payments-for-woocommerce'),
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        unset($generalFormFields[1]);
        unset($generalFormFields['allowed_countries']);

        return $generalFormFields;
    }
}
