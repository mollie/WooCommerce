<?php

return [
    [
        'title' => __( 'Customize Icons', 'mollie-payments-for-woocommerce' ),
        'type'  => 'title',
        'desc'  => '',
        'id'    => 'customize_icons',
    ],
    'mollie_creditcard_icons_enabler' => [
        'type' => 'checkbox',
        'title' => __('Enable Icons Selector', 'mollie-payments-for-woocommerce'),
        'description' => __(
            'Show customized creditcard icons on checkout page',
            'mollie-payments-for-woocommerce'
        ),
        'checkboxgroup'   => 'start',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_amex' => [
        'label'       => __('Show American Express Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_cartasi' => [
        'label'       => __('Show Carta Si Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_cartebancaire' => [
        'label'       => __('Show Carte Bancaire Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_maestro' => [
        'label'       => __('Show Maestro Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_mastercard' => [
        'label'       => __('Show Mastercard Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_visa' => [
        'label'       => __('Show Visa Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'mollie_creditcard_icons_vpay' => [
        'label'       => __('Show VPay Icon', 'mollie-payments-for-woocommerce'),
        'type' => 'checkbox',
        'checkboxgroup'   => 'end',
        'default' => 'no',
    ],
];
