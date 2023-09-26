<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Directdebit extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'directdebit',
            'defaultTitle' => __('SEPA Direct Debit', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __("SEPA Direct Debit is used for recurring payments with WooCommerce Subscriptions, and will not be shown in the WooCommerce checkout for regular payments! You also need to enable iDEAL and/or other 'first' payment methods if you want to use SEPA Direct Debit.", 'mollie-payments-for-woocommerce'),
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => true,
            'SEPA' => false,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        unset($generalFormFields['display_logo']);
        unset($generalFormFields['description']);
        unset($generalFormFields['enable_custom_logo']);
        unset($generalFormFields['upload_logo']);
        unset($generalFormFields['payment_surcharge']);
        unset($generalFormFields['fixed_fee']);
        unset($generalFormFields['percentage']);
        unset($generalFormFields['surcharge_limit']);
        unset($generalFormFields['maximum_limit']);
        unset($generalFormFields[2]);
        unset($generalFormFields[3]);
        return $generalFormFields;
    }
}
