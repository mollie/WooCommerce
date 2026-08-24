<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\PayPalButton;

class PropertiesDictionary
{
    /**
     * @var string[]
     */
    public const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS = [\Mollie\WooCommerce\Buttons\PayPalButton\PropertiesDictionary::NONCE, \Mollie\WooCommerce\Buttons\PayPalButton\PropertiesDictionary::PRODUCT_ID, self::PRODUCT_QUANTITY];
    /**
     * @var string[]
     */
    public const CREATE_ORDER_CART_REQUIRED_FIELDS = [\Mollie\WooCommerce\Buttons\PayPalButton\PropertiesDictionary::NONCE];
    /**
     * @var string
     */
    public const PRODUCT_ID = 'productId';
    /**
     * @var string
     */
    public const NONCE = 'nonce';
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
    public const NEED_SHIPPING = 'needShipping';
    /**
     * @var string
     */
    public const CREATE_ORDER = 'mollie_paypal_create_order';
    /**
     * @var string
     */
    public const CREATE_ORDER_CART = 'mollie_paypal_create_order_cart';
    /**
     * @var string
     */
    public const UPDATE_AMOUNT = 'mollie_paypal_update_amount';
    /**
     * @var string
     */
    public const REDIRECT = 'mollie_paypal_redirect';
}
