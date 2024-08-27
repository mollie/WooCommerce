<?php

$pluginName = 'mollie-payments-for-woocommerce';
$title = 'Apple Pay';
$description = 'Apple description';
$pluginId = 'mollie-payments-for-woocommerce';
$applePayOption = get_option('mollie_wc_gateway_applepay_settings');

return [
    [
        'id' => $title . '_' . 'title',
        'title' => __('Apple Pay', 'mollie-payments-for-woocommerce'),
        'type' => 'title',
        'desc' => '<p>' . __('The following options are required to use the Apple Pay gateway', 'mollie-payments-for-woocommerce') . '</p>',
    ],

    [
        'id' => 'enabled',
        'title' => __('Enable/Disable', 'mollie-payments-for-woocommerce'),
        /* translators: Placeholder 1: Gateway title */
        'desc' => sprintf(__('Enable %s', 'mollie-payments-for-woocommerce'), $title),
        'type' => 'checkbox',
        'default' =>  'yes',
        'value' => isset($applePayOption['enabled']) ? $applePayOption['enabled'] : 'yes',

    ],
    [
        'id' => 'title',
        'title' => __('Title', 'mollie-payments-for-woocommerce'),
        'desc' => sprintf(
            /* translators: Placeholder 1: Gateway title */
            __(
                'This controls the title which the user sees during checkout. Default <code>%s</code>',
                'mollie-payments-for-woocommerce'
            ),
            $title
        ),
        'desc_tip' => true,
        'type' => 'text',
        'default' =>  $title,
        'value' => isset($applePayOption['title']) ? $applePayOption['title'] : $title,

    ],
    [
        'id' => 'display_logo',
        'title' => __('Display logo', 'mollie-payments-for-woocommerce'),
        'desc' => __(
            'Display logo',
            'mollie-payments-for-woocommerce'
        ),
        'desc_tip' => true,
        'type' => 'checkbox',
        'default' => 'yes',
        'value' => isset($applePayOption['display_logo']) ? $applePayOption['display_logo'] : 'yes',

    ],
    [
        'id' => 'description',
        'title' => __('Description', 'mollie-payments-for-woocommerce'),
        'desc' => sprintf(
            /* translators: Placeholder 1: Gateway description */
            __(
                'Payment method description that the customer will see on your checkout. Default <code>%s</code>',
                'mollie-payments-for-woocommerce'
            ),
            $description
        ),
        'desc_tip' => true,
        'type' => 'text',
        'default' => $description,
        'value' => isset($applePayOption['description']) ? $applePayOption['description'] : $description,
    ],
    [
        'id' => $pluginId . '_' . 'sectionend',
        'type' => 'sectionend',
    ],
    [
        'id' => $title . '_' . 'title_button',
        'title' =>  __(
            'Apple Pay button settings',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'title',
        'desc' => '<p>' . __('The following options are required to use the Apple Pay Direct Button', 'mollie-payments-for-woocommerce') . '</p>',
    ],
    [
        'id' => 'mollie_apple_pay_button_enabled_cart',
        'title' => __('Enable Apple Pay Button on Cart page', 'mollie-payments-for-woocommerce'),
        'desc' => sprintf(
        /* translators: Placeholder 1: enabled or disabled */
            __(
                'Enable the Apple Pay direct buy button on the Cart page',
                'mollie-payments-for-woocommerce'
            ),
            $description
        ),
        'type' => 'checkbox',
        'default' => 'no',
        'value' => isset($applePayOption['mollie_apple_pay_button_enabled_cart']) ? $applePayOption['mollie_apple_pay_button_enabled_cart'] : 'no',

    ],
    [
        'id' => 'mollie_apple_pay_button_enabled_product',
        'title' => __('Enable Apple Pay Button on Product page', 'mollie-payments-for-woocommerce'),
        'desc' => sprintf(
        /* translators: Placeholder 1: enabled or disabled */
            __(
                'Enable the Apple Pay direct buy button on the Product page',
                'mollie-payments-for-woocommerce'
            ),
            $description
        ),
        'type' => 'checkbox',
        'default' => 'no',
        'value' => isset($applePayOption['mollie_apple_pay_button_enabled_product']) ? $applePayOption['mollie_apple_pay_button_enabled_product'] : 'no',

    ],
    [
        'id' => $pluginName . '_' . 'sectionend',
        'type' => 'sectionend',
    ],
];
