<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Paypal extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'paypal',
            'defaultTitle' => __('PayPal', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
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
        $paymentMethodFormFieds =  [
            'mollie_paypal_button_enabled_cart' => [
                'type' => 'checkbox',
                'title' => __(
                    'Display on cart page',
                    'mollie-payments-for-woocommerce'
                ),
                'description' => __(
                    'Enable the PayPal button to be used in the cart page.',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'no',
            ],
            'mollie_paypal_button_enabled_product' => [
                'type' => 'checkbox',
                'title' => __(
                    'Display on product page',
                    'mollie-payments-for-woocommerce'
                ),
                'description' => __(
                    'Enable the PayPal button to be used in the product page.',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'no',
            ],
            'color' => [
                'type' => 'select',
                'id' => 'mollie_paypal_buttton_color',
                'title' => _x('Button text language and color', 'Mollie PayPal Button Settings', 'mollie-payments-for-woocommerce'),
                'description' => _x(
                    'Select the text and the colour of the button.',
                    'Mollie PayPal Button Settings',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'buy-gold',
                'options' => $this->buttonOptions(),
            ],
            'mollie_paypal_button_minimum_amount' => [
                'type' => 'number',
                'title' => __(
                    'Minimum amount to display button',
                    'mollie-payments-for-woocommerce'
                ),
                'description' => __(
                    'If the product or the cart total amount is under this number, then the button will not show up.',
                    'mollie-payments-for-woocommerce'
                ),
                'custom_attributes' => ['step' => '0.01', 'min' => '0', 'max' => '100000000'],
                'default' => 0,
                'desc_tip' => true,
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }

    private function buttonOptions(): array
    {
        return [
            'en-buy-pill-blue' => _x('English -- Buy with PayPal - Pill blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-rounded-blue' => _x('English -- Buy with PayPal - Rounded blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-pill-golden' => _x('English -- Buy with PayPal - Pill golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-rounded-golden' => _x('English -- Buy with PayPal - Rounded golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-pill-gray' => _x('English -- Buy with PayPal - Pill gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-rounded-gray' => _x('English -- Buy with PayPal - Rounded gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-pill-white' => _x('English -- Buy with PayPal - Pill white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-buy-rounded-white' => _x('English -- Buy with PayPal - Rounded white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-pill-black' => _x('English -- Checkout with PayPal - Pill black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-rounded-black' => _x('English -- Checkout with PayPal - Rounded black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-pill-blue' => _x('English -- Checkout with PayPal - Pill blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-rounded-blue' => _x('English -- Checkout with PayPal - Rounded blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-pill-golden' => _x('English -- Checkout with PayPal - Pill golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-rounded-golden' => _x('English -- Checkout with PayPal - Rounded golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-pill-gray' => _x('English -- Checkout with PayPal - Pill gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-rounded-gray' => _x('English -- Checkout with PayPal - Rounded gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-pill-white' => _x('English -- Checkout with PayPal - Pill white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'en-checkout-rounded-white' => _x('English -- Checkout with PayPal - Rounded white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-pill-black' => _x('Dutch -- Buy with PayPal - Pill black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-rounded-black' => _x('Dutch -- Buy with PayPal - Rounded black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-pill-blue' => _x('Dutch -- Buy with PayPal - Pill blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-rounded-blue' => _x('Dutch -- Buy with PayPal - Rounded blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-pill-golden' => _x('Dutch -- Buy with PayPal - Pill golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-rounded-golden' => _x('Dutch -- Buy with PayPal - Rounded golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-pill-gray' => _x('Dutch -- Buy with PayPal - Pill gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-rounded-gray' => _x('Dutch -- Buy with PayPal - Rounded gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-pill-white' => _x('Dutch -- Buy with PayPal - Pill white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-buy-rounded-white' => _x('Dutch -- Buy with PayPal - Rounded white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-pill-black' => _x('Dutch -- Checkout with PayPal - Pill black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-rounded-black' => _x('Dutch -- Checkout with PayPal - Rounded black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-pill-blue' => _x('Dutch -- Checkout with PayPal - Pill blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-rounded-blue' => _x('Dutch -- Checkout with PayPal - Rounded blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-pill-golden' => _x('Dutch -- Checkout with PayPal - Pill golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-rounded-golden' => _x('Dutch -- Checkout with PayPal - Rounded golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-pill-gray' => _x('Dutch -- Checkout with PayPal - Pill gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-rounded-gray' => _x('Dutch -- Checkout with PayPal - Rounded gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-pill-white' => _x('Dutch -- Checkout with PayPal - Pill white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl-checkout-rounded-white' => _x('Dutch -- Checkout with PayPal - Rounded white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-pill-black' => _x('German -- Buy with PayPal - Pill black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-rounded-black' => _x('German -- Buy with PayPal - Rounded black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-pill-blue' => _x('German -- Buy with PayPal - Pill blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-rounded-blue' => _x('German -- Buy with PayPal - Rounded blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-pill-golden' => _x('German -- Buy with PayPal - Pill golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-rounded-golden' => _x('German -- Buy with PayPal - Rounded golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-pill-gray' => _x('German -- Buy with PayPal - Pill gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-rounded-gray' => _x('German -- Buy with PayPal - Rounded gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-pill-white' => _x('German -- Buy with PayPal - Pill white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-buy-rounded-white' => _x('German -- Buy with PayPal - Rounded white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-pill-black' => _x('German -- Checkout with PayPal - Pill black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-rounded-black' => _x('German -- Checkout with PayPal - Rounded black', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-pill-blue' => _x('German -- Checkout with PayPal - Pill blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-rounded-blue' => _x('German -- Checkout with PayPal - Rounded blue', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-pill-golden' => _x('German -- Checkout with PayPal - Pill golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-rounded-golden' => _x('German -- Checkout with PayPal - Rounded golden', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-pill-gray' => _x('German -- Checkout with PayPal - Pill gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-rounded-gray' => _x('German -- Checkout with PayPal - Rounded gray', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-pill-white' => _x('German -- Checkout with PayPal - Pill white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de-checkout-rounded-white' => _x('German -- Checkout with PayPal - Rounded white', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'fr-buy-rounded-gold' => _x('French -- Buy with PayPal - Gold', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'fr-checkout-rounded-gold' => _x('French -- Checkout with PayPal - Gold', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'fr-checkout-rounded-silver' => _x('French -- Checkout with PayPal - Silver', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'pl-buy-rounded-gold' => _x('Polish -- Buy with PayPal - Gold', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'pl-checkout-rounded-gold' => _x('Polish -- Checkout with PayPal - Gold', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'pl-checkout-rounded-silver' => _x('Polish -- Checkout with PayPal - Silver', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
        ];
    }
}
