<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use WC_Order;

/**
 * Single accessor for the Mollie payment attempt currently linked to a WooCommerce order.
 *
 * Owns get/set semantics for the attempt meta so the payment processor and the webhook
 * service never read or write `_mollie_payment_id`, `_mollie_order_id` or
 * `_mollie_payment_method` directly. `_mollie_payment_method` records which WooCommerce
 * gateway created the linked payment, independently of the mutable WooCommerce
 * `_payment_method` meta (which a later checkout run overwrites).
 */
class MolliePaymentAttempt
{
    const META_PAYMENT_ID = '_mollie_payment_id';
    const META_ORDER_ID = '_mollie_order_id';
    const META_GATEWAY = '_mollie_payment_method';

    public static function paymentId(WC_Order $order): string
    {
        return (string) $order->get_meta(self::META_PAYMENT_ID, true);
    }

    public static function orderId(WC_Order $order): string
    {
        return (string) $order->get_meta(self::META_ORDER_ID, true);
    }

    /**
     * The WooCommerce gateway id that created the currently linked Mollie payment.
     */
    public static function creatingGateway(WC_Order $order): string
    {
        return (string) $order->get_meta(self::META_GATEWAY, true);
    }

    /**
     * Persist the gateway that created the linked payment. No-op on an empty gateway id.
     */
    public static function rememberCreatingGateway(WC_Order $order, string $gatewayId): void
    {
        if ($gatewayId === '') {
            return;
        }

        $order->update_meta_data(self::META_GATEWAY, $gatewayId);
        $order->save();
    }

    /**
     * Whether $attemptId is the payment/order the order is currently linked to. An empty
     * candidate is never the current attempt.
     */
    public static function isCurrentAttempt(WC_Order $order, string $attemptId): bool
    {
        if ($attemptId === '') {
            return false;
        }

        return $attemptId === (string) $order->get_transaction_id()
            || $attemptId === self::paymentId($order)
            || $attemptId === self::orderId($order);
    }
}
