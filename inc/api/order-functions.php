<?php

/**
 * The API for operations with orders.
 *
 * @package WooCommerce\mollieCommerce\Api
 *
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
 */

declare(strict_types=1);

namespace Mollie\WooCommerce\Inc\Api;

use Mollie\WooCommerce\PluginApi\MolliePluginApi;
use WC_Order;

/**
 * Captures the Mollie order.
 * Logs the result of the operation.
 *
 * @param WC_Order $wc_order The WC order.
 *
 */
function mollie_capture_order(WC_Order $wc_order): void
{

    $mollieApi = MolliePluginApi::getInstance();
    $mollieApi->captureOrder($wc_order);
}

/**
 * Refunds the Mollie order.
 *
 * @param WC_Order $wc_order The WC order.
 * @param float $amount The refund amount.
 * @param string $reason The reason for the refund.
 * @return \WP_Error|bool The result of the refund operation.
 */
function mollie_refund_order(WC_Order $wc_order, float $amount, string $reason = '')
{

    $mollieApi = MolliePluginApi::getInstance();
    return $mollieApi->refundOrder($wc_order, $amount, $reason);
}

/**
 * Voids the authorization.
 * Logs the result of the operation.
 *
 * @param WC_Order $wc_order The WC order.
 *
 */
function mollie_void_order(WC_Order $wc_order): void
{

    $mollieApi = MolliePluginApi::getInstance();
    $mollieApi->voidOrder($wc_order);
}

/**
 * Cancels the order at Mollie and also in WooCommerce if was not already done.
 * Logs the result of the operation.
 *
 * @param WC_Order $wc_order The WC order.
 */
function mollie_cancel_order(WC_Order $wc_order): void
{

    $order_id = $wc_order->get_id();
    $mollieApi = MolliePluginApi::getInstance();
    $mollieApi->cancelOrder((string)$order_id);
}

/**
 * Ship all order lines and capture an order at Mollie.
 * Logs the result of the operation.
 *
 * @param WC_Order $wc_order The WC order.
 *
 */
function mollie_ship_order(WC_Order $wc_order): void
{
    $order_id = $wc_order->get_id();
    $mollieApi = MolliePluginApi::getInstance();
    $mollieApi->shipOrderAndCapture((string)$order_id);
}
