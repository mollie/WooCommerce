<?php

return [
    'mollie_components_enabled' => [
        'type' => 'checkbox',
        'title' => __('Enable Mollie Components', 'mollie-payments-for-woocommerce'),
        'description' => __(
            'Enable the Mollie Components for this Gateway',
            'mollie-payments-for-woocommerce'
        ),
        'default' => 'no',
    ],
];
