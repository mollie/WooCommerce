<?php

class Mollie_WC_ApplePayButton_PropertiesDictionary
{

    const VALIDATION_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::VALIDATION_URL
        ];
    const BILLING_CONTACT_INVALID = 'billing Contact Invalid';
    const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            self::BILLING_CONTACT,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT
        ];
    const UPDATE_METHOD_CART_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_METHOD,
            self::CALLER_PAGE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SIMPLIFIED_CONTACT
        ];
    const UPDATE_CONTACT_CART_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            self::CALLER_PAGE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SIMPLIFIED_CONTACT,
            self::NEED_SHIPPING
        ];
    const UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            self::CALLER_PAGE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SIMPLIFIED_CONTACT,

            self::NEED_SHIPPING
        ];
    const VALIDATION_URL = 'validationUrl';
    const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_METHOD,
            self::CALLER_PAGE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SIMPLIFIED_CONTACT
        ];
    const PRODUCT_ID = 'productId';
    const SIMPLIFIED_CONTACT = 'simplifiedContact';
    const SHIPPING_METHOD = 'shippingMethod';
    const SHIPPING_CONTACT = 'shippingContact';
    const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';
    const NONCE = 'nonce';
    const CREATE_ORDER_CART_REQUIRED_FIELDS
        = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::NONCE,
            Mollie_WC_ApplePayButton_PropertiesDictionary::BILLING_CONTACT,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT
        ];
    const PRODUCT_QUANTITY = 'productQuantity';
    const CALLER_PAGE = 'callerPage';
    const BILLING_CONTACT = 'billingContact';
    const NEED_SHIPPING = 'needShipping';
    const UPDATE_SHIPPING_CONTACT = 'mollie_apple_pay_update_shipping_contact';
    const UPDATE_SHIPPING_METHOD = 'mollie_apple_pay_update_shipping_method';
    const VALIDATION = 'mollie_apple_pay_validation';
    const CREATE_ORDER = 'mollie_apple_pay_create_order';
    const CREATE_ORDER_CART = 'mollie_apple_pay_create_order_cart';
    const REDIRECT = 'mollie_apple_pay_redirect';
}
