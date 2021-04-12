<?php
$pluginName = Mollie_WC_Plugin::PLUGIN_ID;
return [
    [
        'id'    => $pluginName . '_' .'title',
        'title' => __('Mollie advanced settings', 'mollie-payments-for-woocommerce'),
        'type'  => 'title',
        'desc'  => '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'mollie-payments-for-woocommerce') . '</p>',
    ],
    [
        'id'      => $pluginName . '_' .'order_status_cancelled_payments',
        'title'   => __('Order status after cancelled payment', 'mollie-payments-for-woocommerce'),
        'type'    => 'select',
        'options' => array(
            'pending'          => __('Pending', 'woocommerce'),
            'cancelled'     => __('Cancelled', 'woocommerce'),
        ),
        'desc'    => __('Status for orders when a payment (not a Mollie order via the Orders API) is cancelled. Default: pending. Orders with status Pending can be paid with another payment method, customers can try again. Cancelled orders are final. Set this to Cancelled if you only have one payment method or don\'t want customers to re-try paying with a different payment method. This doesn\'t apply to payments for orders via the new Orders API and Klarna payments.', 'mollie-payments-for-woocommerce'),
        'default' => 'pending',
    ],
    [
        'id' =>$pluginName . '_' .Mollie_WC_Helper_Settings::SETTING_NAME_PAYMENT_LOCALE,
        'title'   => __('Payment screen language', 'mollie-payments-for-woocommerce'),
        'type'    => 'select',
        'options' => array(
            Mollie_WC_Helper_Settings::SETTING_LOCALE_WP_LANGUAGE => __(
                    'Automatically send WordPress language',
                    'mollie-payments-for-woocommerce'
                ) . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')',
            Mollie_WC_Helper_Settings::SETTING_LOCALE_DETECT_BY_BROWSER => __(
                'Detect using browser language',
                'mollie-payments-for-woocommerce'
            ),
            'en_US' => __('English', 'mollie-payments-for-woocommerce'),
            'nl_NL' => __('Dutch', 'mollie-payments-for-woocommerce'),
            'nl_BE' => __('Flemish (Belgium)', 'mollie-payments-for-woocommerce'),
            'fr_FR' => __('French', 'mollie-payments-for-woocommerce'),
            'fr_BE' => __('French (Belgium)', 'mollie-payments-for-woocommerce'),
            'de_DE' => __('German', 'mollie-payments-for-woocommerce'),
            'de_AT' => __('Austrian German', 'mollie-payments-for-woocommerce'),
            'de_CH' => __('Swiss German', 'mollie-payments-for-woocommerce'),
            'es_ES' => __('Spanish', 'mollie-payments-for-woocommerce'),
            'ca_ES' => __('Catalan', 'mollie-payments-for-woocommerce'),
            'pt_PT' => __('Portuguese', 'mollie-payments-for-woocommerce'),
            'it_IT' => __('Italian', 'mollie-payments-for-woocommerce'),
            'nb_NO' => __('Norwegian', 'mollie-payments-for-woocommerce'),
            'sv_SE' => __('Swedish', 'mollie-payments-for-woocommerce'),
            'fi_FI' => __('Finnish', 'mollie-payments-for-woocommerce'),
            'da_DK' => __('Danish', 'mollie-payments-for-woocommerce'),
            'is_IS' => __('Icelandic', 'mollie-payments-for-woocommerce'),
            'hu_HU' => __('Hungarian', 'mollie-payments-for-woocommerce'),
            'pl_PL' => __('Polish', 'mollie-payments-for-woocommerce'),
            'lv_LV' => __('Latvian', 'mollie-payments-for-woocommerce'),
            'lt_LT' => __('Lithuanian', 'mollie-payments-for-woocommerce'),
        ),
        'desc'    => sprintf(
            __('Sending a language (or locale) is required. The option \'Automatically send WordPress language\' will try to get the customer\'s language in WordPress (and respects multilanguage plugins) and convert it to a format Mollie understands. If this fails, or if the language is not supported, it will fall back to American English. You can also select one of the locales currently supported by Mollie, that will then be used for all customers.', 'mollie-payments-for-woocommerce'),
            '<a href="https://www.mollie.com/nl/docs/reference/payments/create" target="_blank">',
            '</a>'
        ),
        'default' => Mollie_WC_Helper_Settings::SETTING_LOCALE_WP_LANGUAGE,
    ],
    [
        'id'                => $pluginName . '_' .'customer_details',
        'title'             => __('Store customer details at Mollie', 'mollie-payments-for-woocommerce'),
        /* translators: Placeholder 1: enabled or disabled */
        'desc' => sprintf(
            __(
                'Should Mollie store customers name and email address for Single Click Payments? Default <code>%1$s</code>. Required if WooCommerce Subscriptions is being used! Read more about <a href="https://help.mollie.com/hc/en-us/articles/115000671249-What-are-single-click-payments-and-how-does-it-work-">%2$s</a> and how it improves your conversion.',
                'mollie-payments-for-woocommerce'
            ),
            strtolower(__('Enabled', 'mollie-payments-for-woocommerce')),
            __('Single Click Payments', 'mollie-payments-for-woocommerce')
        ),
        'type'              => 'checkbox',
        'default'           => 'yes',


    ],
    [
        'id'                => $pluginName . '_' .'api_switch',
        'title' => __(
            'Select API Method',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'select',
        'options' => [
            Mollie_WC_Gateway_Abstract::PAYMENT_METHOD_TYPE_ORDER => ucfirst(
                    Mollie_WC_Gateway_Abstract::PAYMENT_METHOD_TYPE_ORDER
                ) . ' (' . __('default', 'mollie-payments-for-woocommerce')
                . ')',
            Mollie_WC_Gateway_Abstract::PAYMENT_METHOD_TYPE_PAYMENT => ucfirst(
                Mollie_WC_Gateway_Abstract::PAYMENT_METHOD_TYPE_PAYMENT
            ),
        ],
        'default' => Mollie_WC_Gateway_Abstract::PAYMENT_METHOD_TYPE_ORDER,
        /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
        'desc' => sprintf(
            __(
                'Click %shere%s to read more about the differences between the Payments and Orders API',
                'mollie-payments-for-woocommerce'
            ),
            '<a href="https://docs.mollie.com/orders/why-use-orders" target="_blank">',
            '</a>'
        )
    ],
    [
        'id' => $pluginName . '_' . 'api_payment_description',
        'title' => __(
            'API Payment Description',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'select',
        'options' => [
            '{orderNumber}' => '{orderNumber}',
            '{storeName}' => '{storeName}',
            '{customer.firstname}' => '{customer.firstname}',
            '{customer.lastname}' => '{customer.lastname}',
            '{customer.company}' => '{customer.company}'
        ],
        'default' => '{orderNumber}',
        /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
        'desc' => sprintf(
            __(
                'Select among the available variables the description to be used for this transaction.%s(Note: this only works when the method is set to Payments API)%s',
                'mollie-payments-for-woocommerce'
            ),
            '<p>',
            '</p>'
        )
    ],
    [
        'id'   => $pluginName . '_' .'sectionend',
        'type' => 'sectionend',
    ]
];

