<?php

namespace Mollie\WooCommerce\Gateway;

use Mollie\Api\Resources\Method;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Order;
use WP_Error;

interface MolliePaymentGatewayI
{
    public function paymentMethod(): PaymentMethodI;

    public function paymentService();

    public function dataService();

    public function pluginId();

    public function initIcon();

    public function get_icon();

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields();

    /**
     * Display fields below payment method in checkout
     */
    public function payment_fields();

    /**
     * Save settings
     *
     * @since 1.0
     */
    public function init_settings();

    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    public function isValidForUse(): bool;

    /**
     * @return Method|null
     */
    public function getMollieMethod();

    /**
     * Save options in admin.
     */
    public function process_admin_options();

    public function admin_options();

    /**
     * Validates the multiselect country field.
     * Overrides the one called by get_field_value() on WooCommerce abstract-wc-settings-api.php
     *
     * @param $key
     * @param $value
     *
     * @return array|string
     */
    public function validate_multi_select_countries_field($key, $value);

    /**
     * Check if the gateway is available for use
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Check if payment method is available in checkout based on amount, currency and sequenceType
     *
     * @param $filters
     *
     * @return bool
     */
    public function isAvailableMethodInCheckout($filters): bool;

    /**
     * @return array|false|int
     */
    public function get_recurring_total();

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function process_payment($orderId);

    /**
     * @param $order
     * @param $payment
     */
    public function handlePaidOrderWebhook($order, $payment);

    /**
     * @param WC_Order $order
     *
     * @return string
     */
    public function getReturnRedirectUrlForOrder(WC_Order $order): string;

    /**
     * Process a refund if supported
     *
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     *
     * @return bool|wp_error True or false based on success, or a WP_Error object
     * @since WooCommerce 2.2
     */
    public function process_refund($order_id, $amount = null, $reason = '');

    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id);

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool $admin_instructions (default: false)
     * @param bool $plain_text (default: false)
     *
     * @return void
     */
    public function displayInstructions(WC_Order $order, bool $admin_instructions = false, bool $plain_text = false);

    /**
     * @param      $title
     * @param null $id
     *
     * @return string|void
     */
    public function onOrderReceivedTitle($title, $id = null);

    /**
     * @param          $text
     * @param WC_Order| null $order
     *
     * @return string|void
     */
    public function onOrderReceivedText($text, $order);

    /**
     * @return string|NULL
     */
    public function getSelectedIssuer(): ?string;

    /**
     * Get the transaction URL.
     *
     * @param WC_Order $order
     *
     * @return string
     */
    public function get_transaction_url($order): string;

    /**
     * Get the correct currency for this payment or order
     * On order-pay page, order is already created and has an order currency
     * On checkout, order is not created, use get_woocommerce_currency
     *
     * @return string
     */
    public function getCurrencyFromOrder();

    /**
     * Retrieve the customer's billing country
     * or fallback to the shop country
     *
     * @return mixed|void|null
     */
    public function getBillingCountry();
}
