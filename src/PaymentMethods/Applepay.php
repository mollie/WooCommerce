<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Applepay extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'applepay',
            'defaultTitle' => __('Apple Pay', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __('To accept payments via Apple Pay', 'mollie-payments-for-woocommerce'),
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
                'subscriptions',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false,
            'Subscription' => true,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        $paymentMethodFormFieds = [
            'mollie_apple_pay_button_enabled_cart' => [
                'title' => __('Enable Apple Pay Button on Cart page', 'mollie-payments-for-woocommerce'),
                /* translators: Placeholder 1: enabled or disabled */
                'desc' => __(
                    'Enable the Apple Pay direct buy button on the Cart page',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_apple_pay_button_enabled_product' => [
                'title' => __('Enable Apple Pay Button on Product page', 'mollie-payments-for-woocommerce'),
                /* translators: Placeholder 1: enabled or disabled */
                'desc' => __(
                    'Enable the Apple Pay direct buy button on the Product page',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'default' => 'no',
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }
}
