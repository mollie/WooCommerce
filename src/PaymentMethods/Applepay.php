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
            'docs' => 'https://www.mollie.com/gb/payments/apple-pay',
        ];
    }

    public function getFormFields($generalFormFields): array
    {

        $checkout_page_id = wc_get_page_id('checkout');
        $edit_checkout_page_link = get_edit_post_link($checkout_page_id);

        $notice = [
            'notice' => [
                'title' => sprintf(
                    /* translators: Placeholder 1: link url */
                    __(
                        '<p>The appearance of the Apple Pay button can be controlled in the <a href="%1$s">Checkout page editor</a>.</p>',
                        'mollie-payments-for-woocommerce'
                    ),
                    esc_url($edit_checkout_page_link)
                ),
                'type' => 'title',
                'class' => 'notice notice-warning',
                'css' => 'padding:20px;',
            ],
        ];
        $paymentMethodFormFieds = [
            'mollie_apple_pay_button_enabled_cart' => [
                'title' => __('Enable Apple Pay Button on Cart page', 'mollie-payments-for-woocommerce'),
                'desc' => __(
                    'Enable the Apple Pay direct buy button on the Cart page',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_apple_pay_button_enabled_product' => [
                'title' => __('Enable Apple Pay Button on Product page', 'mollie-payments-for-woocommerce'),
                'desc' => __(
                    'Enable the Apple Pay direct buy button on the Product page',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_apple_pay_button_enabled_express_checkout' => [
                'title' => __('Enable Apple Pay Express Button on Checkout page', 'mollie-payments-for-woocommerce'),
                'desc' => __('Enable the Apple Pay direct buy button on the Express Buttons section of the Checkout page', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
        ];
        return array_merge($notice, $generalFormFields, $paymentMethodFormFieds);
    }
}
