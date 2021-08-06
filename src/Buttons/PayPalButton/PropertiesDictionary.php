<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\PayPalButton;

class PropertiesDictionary
{
    /**
     * @var string[]
     */
    const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE,
            PropertiesDictionary::PRODUCT_ID,
            self::PRODUCT_QUANTITY
        ];
    /**
     * @var string[]
     */
    const CREATE_ORDER_CART_REQUIRED_FIELDS
        = [
            PropertiesDictionary::NONCE
        ];

    /**
     * @var string
     */
    const PRODUCT_ID = 'productId';
    /**
     * @var string
     */
    const NONCE = 'nonce';
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
    const NEED_SHIPPING = 'needShipping';
    /**
     * @var string
     */
    const CREATE_ORDER = 'mollie_paypal_create_order';
    /**
     * @var string
     */
    const CREATE_ORDER_CART = 'mollie_paypal_create_order_cart';
    /**
     * @var string
     */
    const UPDATE_AMOUNT = 'mollie_paypal_update_amount';
    /**
     * @var string
     */
    const REDIRECT = 'mollie_paypal_redirect';
}
