<?php
$pluginName = Mollie_WC_Plugin::PLUGIN_ID;
return [
    [
        'id' => $pluginName . '_' . 'title',
        'title' => __(
            'Apple Pay button settings',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'title',
        'desc' => '<p>' . __(
                'The following options are required to use the Apple Pay button',
                'mollie-payments-for-woocommerce'
            ) . '</p>',
    ],

    'mollie_apple_pay_button_enabled' => [
        'type' => 'checkbox',
        'title' => __(
            'Enable Apple Pay Button',
            'mollie-payments-for-woocommerce'
        ),
        'description' => __(
            'Enable the Apple Pay direct buy button',
            'mollie-payments-for-woocommerce'
        ),
        'default' => 'no',
    ]

];
