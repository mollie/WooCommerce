<?php

return [
    'mollie_apple_pay_button_enabled' => [
        'type' => 'checkbox',
        'title' => __('Enable Apple Pay Button', 'mollie-payments-for-woocommerce'),
        'description' => __(
            'Enable the Apple Pay direct buy button',
            'mollie-payments-for-woocommerce'
        ),
        'default' => 'no',
    ],
];
