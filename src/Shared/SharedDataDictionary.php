<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

class SharedDataDictionary
{
    public const GATEWAY_CLASSNAMES = [
        'Mollie_WC_Gateway_BankTransfer',
        'Mollie_WC_Gateway_Belfius',
        'Mollie_WC_Gateway_Creditcard',
        'Mollie_WC_Gateway_DirectDebit',
        'Mollie_WC_Gateway_EPS',
        'Mollie_WC_Gateway_Giropay',
        'Mollie_WC_Gateway_Ideal',
        'Mollie_WC_Gateway_Kbc',
        'Mollie_WC_Gateway_KlarnaPayLater',
        'Mollie_WC_Gateway_KlarnaSliceIt',
        'Mollie_WC_Gateway_KlarnaPayNow',
        'Mollie_WC_Gateway_Bancontact',
        'Mollie_WC_Gateway_PayPal',
        'Mollie_WC_Gateway_Paysafecard',
        'Mollie_WC_Gateway_Przelewy24',
        'Mollie_WC_Gateway_Sofort',
        'Mollie_WC_Gateway_Giftcard',
        'Mollie_WC_Gateway_ApplePay',
        'Mollie_WC_Gateway_MyBank',
        'Mollie_WC_Gateway_Voucher',
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
        'mollie-payments-for-woocommerce_removeOptionsAndTransients'
    ];
}
