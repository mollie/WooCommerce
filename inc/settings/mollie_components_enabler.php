<?php

return [
    'mollie_components_enabled' => [
        'type' => 'checkbox',
        'title' => __('Enable Mollie Components', 'mollie-payments-for-woocommerce'),
        'description' => sprintf(
            __(
                'Use the Mollie Components for this Gateway. Read more about <a href="https://www.mollie.com/en/news/post/better-checkout-flows-with-mollie-components">%s</a> and how it improves your conversion.',
                'mollie-payments-for-woocommerce'
            ),
            __('Mollie Components', 'mollie-payments-for-woocommerce')

        ),
        'default' => 'no',
    ],
];
