<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

class PropertiesDictionary
{

    /**
     * @var string[]
     */
    const VALIDATION_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE,
            PropertiesDictionary::VALIDATION_URL
        ];
    /**
     * @var string
     */
    const BILLING_CONTACT_INVALID = 'billing Contact Invalid';
    /**
     * @var string[]
     */
    const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS
        = [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            self::BILLING_CONTACT,
            PropertiesDictionary::SHIPPING_CONTACT
        ];
    /**
     * @var string[]
     */
    const UPDATE_METHOD_CART_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE,
            PropertiesDictionary::SHIPPING_METHOD,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT
        ];
    /**
     * @var string[]
     */
    const UPDATE_CONTACT_CART_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT,
            self::NEED_SHIPPING
        ];
    /**
     * @var string[]
     */
    const UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT,

            self::NEED_SHIPPING
        ];
    /**
     * @var string
     */
    const VALIDATION_URL = 'validationUrl';
    /**
     * @var string[]
     */
    const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            PropertiesDictionary::SHIPPING_METHOD,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT
        ];
    /**
     * @var string
     */
    const PRODUCT_ID = 'productId';
    /**
     * @var string
     */
    const SIMPLIFIED_CONTACT = 'simplifiedContact';
    /**
     * @var string
     */
    const SHIPPING_METHOD = 'shippingMethod';
    /**
     * @var string
     */
    const SHIPPING_CONTACT = 'shippingContact';
    /**
     * @var string
     */
    const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';
    /**
     * @var string
     */
    const NONCE = 'nonce';
    /**
     * @var string
     */
    const WCNONCE = 'woocommerce-process-checkout-nonce';
    /**
     * @var string[]
     */
    const CREATE_ORDER_CART_REQUIRED_FIELDS
        = [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::BILLING_CONTACT,
            PropertiesDictionary::SHIPPING_CONTACT
        ];
    /**
     * @var string
     */
    const PRODUCT_QUANTITY = 'productQuantity';
    /**
     * @var string
     */
    const CALLER_PAGE = 'callerPage';
    /**
     * @var string
     */
    const BILLING_CONTACT = 'billingContact';
    /**
     * @var string
     */
    const NEED_SHIPPING = 'needShipping';
    /**
     * @var string
     */
    const UPDATE_SHIPPING_CONTACT = 'mollie_apple_pay_update_shipping_contact';
    /**
     * @var string
     */
    const UPDATE_SHIPPING_METHOD = 'mollie_apple_pay_update_shipping_method';
    /**
     * @var string
     */
    const VALIDATION = 'mollie_apple_pay_validation';
    /**
     * @var string
     */
    const CREATE_ORDER = 'mollie_apple_pay_create_order';
    /**
     * @var string
     */
    const CREATE_ORDER_CART = 'mollie_apple_pay_create_order_cart';
    /**
     * @var string
     */
    const REDIRECT = 'mollie_apple_pay_redirect';
}
