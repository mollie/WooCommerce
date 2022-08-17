<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

class PropertiesDictionary
{
    /**
     * @var string[]
     */
    public const VALIDATION_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::VALIDATION_URL,
        ];
    /**
     * @var string
     */
    public const BILLING_CONTACT_INVALID = 'billing Contact Invalid';
    /**
     * @var string[]
     */
    public const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            self::BILLING_CONTACT,
            PropertiesDictionary::SHIPPING_CONTACT,
        ];
    /**
     * @var string[]
     */
    public const UPDATE_METHOD_CART_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::SHIPPING_METHOD,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT,
        ];
    /**
     * @var string[]
     */
    public const UPDATE_CONTACT_CART_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT,
            self::NEED_SHIPPING,
        ];
    /**
     * @var string[]
     */
    public const UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT,
            self::NEED_SHIPPING,
        ];
    /**
     * @var string
     */
    public const VALIDATION_URL = 'validationUrl';
    /**
     * @var string[]
     */
    public const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY,
            PropertiesDictionary::SHIPPING_METHOD,
            self::CALLER_PAGE,
            PropertiesDictionary::SIMPLIFIED_CONTACT,
        ];
    /**
     * @var string
     */
    public const PRODUCT_ID = 'productId';
    /**
     * @var string
     */
    public const SIMPLIFIED_CONTACT = 'simplifiedContact';
    /**
     * @var string
     */
    public const SHIPPING_METHOD = 'shippingMethod';
    /**
     * @var string
     */
    public const SHIPPING_CONTACT = 'shippingContact';
    /**
     * @var string
     */
    public const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';
    /**
     * @var string
     */
    public const NONCE = 'nonce';
    /**
     * @var string
     */
    public const WCNONCE = 'woocommerce-process-checkout-nonce';
    /**
     * @var string[]
     */
    public const CREATE_ORDER_CART_REQUIRED_FIELDS =
        [
            PropertiesDictionary::WCNONCE,
            PropertiesDictionary::BILLING_CONTACT,
            PropertiesDictionary::SHIPPING_CONTACT,
        ];
    /**
     * @var string
     */
    public const PRODUCT_QUANTITY = 'productQuantity';
    /**
     * @var string
     */
    public const CALLER_PAGE = 'callerPage';
    /**
     * @var string
     */
    public const BILLING_CONTACT = 'billingContact';
    /**
     * @var string
     */
    public const NEED_SHIPPING = 'needShipping';
    /**
     * @var string
     */
    public const UPDATE_SHIPPING_CONTACT = 'mollie_apple_pay_update_shipping_contact';
    /**
     * @var string
     */
    public const UPDATE_SHIPPING_METHOD = 'mollie_apple_pay_update_shipping_method';
    /**
     * @var string
     */
    public const VALIDATION = 'mollie_apple_pay_validation';
    /**
     * @var string
     */
    public const CREATE_ORDER = 'mollie_apple_pay_create_order';
    /**
     * @var string
     */
    public const CREATE_ORDER_CART = 'mollie_apple_pay_create_order_cart';
    /**
     * @var string
     */
    public const REDIRECT = 'mollie_apple_pay_redirect';
}
