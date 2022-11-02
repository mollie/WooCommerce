<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Klarnapaylater extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'klarnapaylater',
            'defaultTitle' => __('Klarna Pay later', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __(
                'To accept payments via Klarna, all default WooCommerce checkout fields should be enabled and required.',
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
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
