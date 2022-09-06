<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Kbc extends AbstractPaymentMethod implements PaymentMethodI
{
    protected const DEFAULT_ISSUERS_DROPDOWN = 'yes';
    protected function getConfig(): array
    {
        return [
            'id' => 'kbc',
            'defaultTitle' => __('KBC/CBC Payment Button', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => false,
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
        $paymentMethodFormFieds =   [
            'issuers_dropdown_shown' => [
                'title' => __(
                    'Show KBC/CBC banks dropdown',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'description' => sprintf(
                    __(
                        'If you disable this, a dropdown with various KBC/CBC banks will not be shown in the WooCommerce checkout, so users will select a KBC/CBC bank on the Mollie payment page after checkout.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getConfig()['defaultTitle']
                ),
                'default' => self::DEFAULT_ISSUERS_DROPDOWN,
            ],
            'issuers_empty_option' => [
                'title' => __(
                    'Issuers empty option',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'text',
                'description' => sprintf(
                    __(
                        'This text will be displayed as the first option in the KBC/CBC issuers drop down, if nothing is entered, "Select your bank" will be shown. Only if the above \'\'Show KBC/CBC banks dropdown\' is enabled.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getConfig()['defaultTitle']
                ),
                'default' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }
}
