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

    'mollie_apple_pay_button_enabled_cart'=>[
        'title'             => __('Enable Apple Pay Button on Cart page', 'mollie-payments-for-woocommerce'),
        /* translators: Placeholder 1: enabled or disabled */
        'desc'              => sprintf(
            __(
                'Enable the Apple Pay direct buy button on the Cart page',
                'mollie-payments-for-woocommerce'
            )),
        'type'              => 'checkbox',
        'default'           => 'no'
    ],
    'mollie_apple_pay_button_enabled_product'=>[
        'title'             => __('Enable Apple Pay Button on Product page', 'mollie-payments-for-woocommerce'),
        /* translators: Placeholder 1: enabled or disabled */
        'desc'              => sprintf(
            __(
                'Enable the Apple Pay direct buy button on the Product page',
                'mollie-payments-for-woocommerce'
            )),
        'type'              => 'checkbox',
        'default'           => 'no'
    ]

];
