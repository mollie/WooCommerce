<?php
$pluginName = Mollie_WC_Plugin::PLUGIN_ID;
return [
    [
        'id' => $pluginName . '_' . 'title',
        'title' => __(
            'PayPay button display settings',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'title',
        'description' => '<p>' . __(
                'The PayPal button is optimized for digital goods. And will only appear if the product has no shipping.',
                'mollie-payments-for-woocommerce'
            ) . '</p>',
    ],
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
        'title' => _x('Button text and color', 'Mollie PayPal Button Settings', 'mollie-payments-for-woocommerce'),
        'description' => sprintf(
            _x(
                'Select the text and the colour of the button.',
                'Mollie PayPal Button Settings',
                'mollie-payments-for-woocommerce'
            )
        ),
        'default' => 'buy-gold',
        'options' => [
            'buy-gold' => _x('Buy with PayPal - Gold', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'checkout-gold' => _x('Checkout with PayPal - Gold', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'checkout-silver' => _x('Checkout with PayPal - Silver', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
        ],
    ],
    'language' => [
        'type' => 'select',
        'id' => 'mollie_paypal_buttton_language',
        'title' => _x('Translate button text into:', 'Mollie PayPal Button Settings', 'mollie-payments-for-woocommerce'),
        'description' => sprintf(
            _x(
                'Choose the language for the text in the button.',
                'Mollie PayPal Button Settings',
                'mollie-payments-for-woocommerce'
            )
        ),
        'default' => 'en',
        'options' => [
            'en' => _x('English', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'nl' => _x('Dutch', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'fr' => _x('French', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'de' => _x('German', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
            'pl' => _x('Polish', 'Mollie PayPal button Settings', 'mollie-payments-for-woocommerce'),
        ],

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
        'custom_attributes'=>['step'=>'0.01', 'min'=>'0', 'max'=>'100000000'],
        'default' => 0,
        'desc_tip' => true,
    ]
];
