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
            'filtersOnBuild' => true,
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

    public function filtersOnBuild()
    {
        add_filter('woocommerce_mollie_wc_gateway_' . $this->getProperty('id') . 'payment_args', function (array $args, \WC_Order $order): array {
            return $this->addPaymentArguments($args, $order);
        }, 10, 2);
    }
    /**
     * @param WC_Order $order
     * @return array
     */
    public function addPaymentArguments(array $args, $order)
    {
        $phone = $order->get_billing_phone();
        if (!empty($phone)) {
            $args['billingAddress']['phone'] = $phone;
        }

        return $args;
    }
}
