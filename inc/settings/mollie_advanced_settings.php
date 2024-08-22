<?php

use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\SharedDataDictionary;

$pluginName = 'mollie-payments-for-woocommerce';
$nonce_mollie_cleanDb = wp_create_nonce('nonce_mollie_cleanDb');
$cleanDB_mollie_url = add_query_arg(
    ['cleanDB-mollie' => 1, 'nonce_mollie_cleanDb' => $nonce_mollie_cleanDb]
);
$api_payment_description_labels = [
    '{orderNumber}' => _x('Order number', 'Label {orderNumber} description for payment description options', 'mollie-payments-for-woocommerce'),
    '{storeName}' => _x('Site Title', 'Label {storeName} description for payment description options', 'mollie-payments-for-woocommerce'),
    '{customer.firstname}' => _x('Customer\'s first name', 'Label {customer.firstname} description for payment description options', 'mollie-payments-for-woocommerce'),
    '{customer.lastname}' => _x('Customer\'s last name', 'Label {customer.lastname} description for payment description options', 'mollie-payments-for-woocommerce'),
    '{customer.company}' => _x('Customer\'s company name', 'Label {customer.company} description for payment description options', 'mollie-payments-for-woocommerce'),
];

$mollieAdvancedSettings =  [
    [
        'id' => $pluginName . '_title',
        'title' => __('Mollie advanced settings', 'mollie-payments-for-woocommerce'),
        'type' => 'title',
        'desc' => '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'mollie-payments-for-woocommerce') . '</p>',
    ],
    [
        'id' => $pluginName . '_order_status_cancelled_payments',
        'title' => __('Order status after cancelled payment', 'mollie-payments-for-woocommerce'),
        'type' => 'select',
        'options' => [
            'pending' => __('Pending', 'woocommerce'),
            'cancelled' => __('Cancelled', 'woocommerce'),
        ],
        'desc' => __('Status for orders when a payment (not a Mollie order via the Orders API) is cancelled. Default: pending. Orders with status Pending can be paid with another payment method, customers can try again. Cancelled orders are final. Set this to Cancelled if you only have one payment method or don\'t want customers to re-try paying with a different payment method. This doesn\'t apply to payments for orders via the new Orders API and Klarna payments.', 'mollie-payments-for-woocommerce'),
        'default' => 'pending',
    ],
    [
        'id' => $pluginName . '_' . SharedDataDictionary::SETTING_NAME_PAYMENT_LOCALE,
        'title' => __('Payment screen language', 'mollie-payments-for-woocommerce'),
        'type' => 'select',
        'options' => [
            SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE => __(
                'Automatically send WordPress language',
                'mollie-payments-for-woocommerce'
            ) . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')',
            SharedDataDictionary::SETTING_LOCALE_DETECT_BY_BROWSER => __(
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
        ],
        'desc' => sprintf(
        /* translators: Placeholder 1: link tag Placeholder 2: closing tag */
            __('Sending a language (or locale) is required. The option \'Automatically send WordPress language\' will try to get the customer\'s language in WordPress (and respects multilanguage plugins) and convert it to a format Mollie understands. If this fails, or if the language is not supported, it will fall back to American English. You can also select one of the locales currently supported by Mollie, that will then be used for all customers.', 'mollie-payments-for-woocommerce'),
            '<a href="https://www.mollie.com/nl/docs/reference/payments/create" target="_blank">',
            '</a>'
        ),
        'default' => SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE,
    ],
    [
        'id' => $pluginName . '_customer_details',
        'title' => __('Store customer details at Mollie', 'mollie-payments-for-woocommerce'),
        'desc' => sprintf(
        /* translators: Placeholder 1: enabled or disabled Placeholder 2: translated string */
            __(
                'Should Mollie store customers name and email address for Single Click Payments? Default <code>%1$s</code>. Required if WooCommerce Subscriptions is being used! Read more about <a href=\'https://help.mollie.com/hc/en-us/articles/115000671249-What-are-single-click-payments-and-how-does-it-work-\'>%2$s</a> and how it improves your conversion.',
                'mollie-payments-for-woocommerce'
            ),
            strtolower(__('Enabled', 'mollie-payments-for-woocommerce')),
            __('Single Click Payments', 'mollie-payments-for-woocommerce')
        ),
        'type' => 'checkbox',
        'default' => 'yes',

    ],
    [
        'id' => $pluginName . '_api_switch',
        'title' => __(
            'Select API Method',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'select',
        'options' => [
            PaymentService::PAYMENT_METHOD_TYPE_ORDER => ucfirst(
                PaymentService::PAYMENT_METHOD_TYPE_ORDER
            ) . ' (' . __('default', 'mollie-payments-for-woocommerce')
                . ')',
            PaymentService::PAYMENT_METHOD_TYPE_PAYMENT => ucfirst(
                PaymentService::PAYMENT_METHOD_TYPE_PAYMENT
            ),
        ],
        'default' => PaymentService::PAYMENT_METHOD_TYPE_ORDER,
        'desc' => sprintf(
        /* translators: Placeholder 1: opening link tag, placeholder 2: closing link tag */
            __(
                'Click %1$shere%2$s to read more about the differences between the Payments and Orders API',
                'mollie-payments-for-woocommerce'
            ),
            '<a href="https://docs.mollie.com/orders/why-use-orders" target="_blank">',
            '</a>'
        ),
    ],
    [
        'id' => $pluginName . '_api_payment_description',
        'title' => __(
            'API Payment Description',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'text',
        'default' => '{orderNumber}',
        'desc' => sprintf(
            '</p>
            <div class="available-payment-description-labels hide-if-no-js">
                <p>%1$s:</p>
                <ul role="list">
                    %2$s
                </ul>
            </div>
            <br style="clear: both;" />
            <p class="description">%3$s',
            _x('Available variables', 'Payment description options', 'mollie-payments-for-woocommerce'),
            implode('', array_map(
                static function ($label, $label_description) {
                    return sprintf(
                        '<li style="float: left; margin-right: 5px;">
                            <button type="button"
                                class="mollie-settings-advanced-payment-desc-label button button-secondary button-small"
                                data-tag="%1$s"
                                aria-label="%2$s"
                                title="%3$s"
                            >
                                %1$s
                            </button>
                        </li>',
                        $label,
                        substr($label, 1, -1),
                        $label_description
                    );
                },
                array_keys($api_payment_description_labels),
                $api_payment_description_labels
            )),
            sprintf(
            /* translators: Placeholder 1: Opening paragraph tag, placeholder 2: Closing paragraph tag */
                __(
                    'Select among the available variables the description to be used for this transaction.%1$s(Note: this only works when the method is set to Payments API)%2$s',
                    'mollie-payments-for-woocommerce'
                ),
                '<p>',
                '</p>'
            )
        ),
    ],
    [
        'id' => $pluginName . '_gatewayFeeLabel',
        'title' => __(
            'Surcharge gateway fee label',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'text',
        'custom_attributes' => ['maxlength' => '30'],
        'default' => __('Gateway Fee', 'mollie-payments-for-woocommerce'),
        'desc' => __(
            'This is the label will appear in frontend when the surcharge applies',
            'mollie-payments-for-woocommerce'
        ),
    ],
    [
        'id' => $pluginName . '_removeOptionsAndTransients',
        'title' => __(
            'Remove Mollie data from Database on uninstall',
            'mollie-payments-for-woocommerce'
        ),
        'type' => 'checkbox',
        'default' => 'no',
        'desc' => __("Remove options and scheduled actions from database when uninstalling the plugin.", "mollie-payments-for-woocommerce") . ' (<a href="' . esc_url($cleanDB_mollie_url) . '">' . strtolower(
            __('Clear now', 'mollie-payments-for-woocommerce')
        ) . '</a>)',
    ],
    [
        'id' => $pluginName . '_sectionend',
        'type' => 'sectionend',
    ],
];

return apply_filters('inpsyde.mollie-advanced-settings', $mollieAdvancedSettings, $pluginName);
