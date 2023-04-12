<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

class SharedDataDictionary
{
    public const DIRECTDEBIT = 'mollie_wc_gateway_directdebit';
    public const GATEWAY_CLASSNAMES = [
        'Mollie_WC_Gateway_Applepay',
        'Mollie_WC_Gateway_Ideal',
        'Mollie_WC_Gateway_Creditcard',
        'Mollie_WC_Gateway_In3',
        'Mollie_WC_Gateway_Klarnapaylater',
        'Mollie_WC_Gateway_Klarnapaynow',
        'Mollie_WC_Gateway_Klarnasliceit',
        'Mollie_WC_Gateway_Billie',
        'Mollie_WC_Gateway_Paypal',
        'Mollie_WC_Gateway_Banktransfer',
        'Mollie_WC_Gateway_Sofort',
        'Mollie_WC_Gateway_Giftcard',
        'Mollie_WC_Gateway_Mybank',
        'Mollie_WC_Gateway_Bancontact',
        'Mollie_WC_Gateway_Eps',
        'Mollie_WC_Gateway_Giropay',
        'Mollie_WC_Gateway_Przelewy24',
        'Mollie_WC_Gateway_Kbc',
        'Mollie_WC_Gateway_Belfius',
        'Mollie_WC_Gateway_Paysafecard',
        'Mollie_WC_Gateway_Voucher',
        'Mollie_WC_Gateway_Directdebit',
    ];

    public const MOLLIE_OPTIONS_NAMES = [
        'mollie_components_::placeholder',
        'mollie_components_backgroundColor',
        'mollie_components_color',
        'mollie_components_fontSize',
        'mollie_components_fontWeight',
        'mollie_components_invalid_backgroundColor',
        'mollie_components_invalid_color',
        'mollie_components_letterSpacing',
        'mollie_components_lineHeight',
        'mollie_components_padding',
        'mollie_components_textAlign',
        'mollie_components_textTransform',
        'mollie_wc_fix_subscriptions',
        'mollie_wc_fix_subscriptions2',
        'mollie-db-version',
        'mollie-payments-for-woocommerce_api_payment_description',
        'mollie-payments-for-woocommerce_api_switch',
        'mollie-payments-for-woocommerce_customer_details',
        'mollie-payments-for-woocommerce_debug',
        'mollie-payments-for-woocommerce_gatewayFeeLabel',
        'mollie-payments-for-woocommerce_live_api_key',
        'mollie-payments-for-woocommerce_order_status_cancelled_payments',
        'mollie-payments-for-woocommerce_payment_locale',
        'mollie-payments-for-woocommerce_profile_merchant_id',
        'mollie-payments-for-woocommerce_test_api_key',
        'mollie-payments-for-woocommerce_test_mode_enabled',
        'mollie_apple_pay_button_enabled_product',
        'mollie_apple_pay_button_enabled_cart',
        'mollie_wc_applepay_validated',
        'mollie-payments-for-woocommerce_removeOptionsAndTransients',
        'mollie-plugin-version',
        'mollie-new-install',
    ];
    public const DB_VERSION_PARAM_NAME = 'mollie-db-version';
    public const PLUGIN_VERSION_PARAM_NAME = 'mollie-plugin-version';
    public const NEW_INSTALL_PARAM_NAME = 'mollie-new-install';
    public const PENDING_PAYMENT_DB_TABLE_NAME = 'mollie_pending_payment';
    public const DB_VERSION = '1.0';

    /**
     * WooCommerce default statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ON_HOLD = 'on-hold';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';
    /**
     * @var string
     */
    public const FILTER_ALLOWED_LANGUAGE_CODE_SETTING = 'mollie.allowed_language_code_setting';
    /**
     * @var string
     */
    public const DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK = '3:00';
    /**
     * @var string
     */
    public const FILTER_WPML_CURRENT_LOCALE = 'wpml_current_language';
    /**
     * @var string
     */
    public const SETTING_LOCALE_WP_LANGUAGE = 'wp_locale';
    /**
     * @var string
     */
    public const SETTING_LOCALE_DETECT_BY_BROWSER = 'detect_by_browser';
    /**
     * @var string[]
     */
    public const ALLOWED_LANGUAGE_CODES = [
        'en_US',
        'nl_NL',
        'nl_BE',
        'fr_FR',
        'fr_BE',
        'de_DE',
        'de_AT',
        'de_CH',
        'es_ES',
        'ca_ES',
        'pt_PT',
        'it_IT',
        'nb_NO',
        'sv_SE',
        'fi_FI',
        'da_DK',
        'is_IS',
        'hu_HU',
        'pl_PL',
        'lv_LV',
        'lt_LT',
    ];
    /**
     * @var string
     */
    public const SETTING_NAME_PAYMENT_LOCALE = 'payment_locale';
    /**
     * @var string
     */
    public const SETTING_LOCALE_DEFAULT_LANGUAGE = 'en_US';
}
