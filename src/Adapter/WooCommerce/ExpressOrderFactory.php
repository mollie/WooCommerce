<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WooCommerce;

use RuntimeException;
use WC_Order;

/**
 * Creates the pending order of an express checkout the way WooCommerce's own checkout does:
 * WC()->checkout()->create_order() from the server-side cart, the customer's details and the chosen
 * shipping rates. Everything written on the order afterwards is an Effect.
 *
 * WooCommerce's validation runs first, so stock that ran out or a coupon that stopped applying is
 * refused with WooCommerce's own words before money moves. An order that was saved and
 * then failed is deleted, so a failure leaves nothing behind.
 */
class ExpressOrderFactory
{
    /**
     * WooCommerce's reason the cart cannot be ordered as it is, or null when it can. The notices
     * WooCommerce raised are taken, so they are not shown again on the next page.
     */
    public function invalidCartReason(): ?string
    {
        $before = wc_get_notices('error');
        $valid = WC()->cart->check_cart_items();
        $raised = array_slice(wc_get_notices('error'), count($before));
        if ($valid !== false && $raised === []) {
            return null;
        }
        wc_set_notices(array_merge(wc_get_notices(), ['error' => $before]));

        $first = $raised[0] ?? null;
        $text = is_array($first) ? (string) ($first['notice'] ?? '') : (string) $first;
        $text = trim(html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, 'UTF-8'));

        return $text !== '' ? $text : __('Your cart cannot be ordered as it is.', 'mollie-payments-for-woocommerce');
    }

    /**
     * @param array{billing: array<string, string>, shipping: array<string, string>} $details What the
     *        store holds for this shopper, in WooCommerce field names without the prefix.
     * @throws RuntimeException When WooCommerce did not create the order; nothing is left behind.
     */
    public function create(array $details): WC_Order
    {
        $created = null;
        $capture = static function (WC_Order $order) use (&$created): void {
            $created = $order;
        };
        add_action('woocommerce_checkout_create_order', $capture, PHP_INT_MIN, 1);

        // create_order() resumes the session's order awaiting payment when the cart hash matches; an
        // express checkout must never take over an order another checkout started.
        $session = WC()->session;
        $awaiting = $session->get('order_awaiting_payment');
        $session->set('order_awaiting_payment', null);

        try {
            $orderId = WC()->checkout()->create_order($this->orderData($details));
        } catch (\Throwable $error) {
            $orderId = null;
        } finally {
            remove_action('woocommerce_checkout_create_order', $capture, PHP_INT_MIN);
            $session->set('order_awaiting_payment', $awaiting);
        }

        $order = is_int($orderId) && $orderId > 0 ? wc_get_order($orderId) : null;
        if (!$order instanceof WC_Order) {
            if ($created instanceof WC_Order && $created->get_id() > 0) {
                $this->delete($created);
            }
            throw new RuntimeException('WooCommerce did not create the express order.');
        }

        return $order;
    }

    public function delete(WC_Order $order): void
    {
        $order->delete(true);
    }

    /**
     * The shopper's details in create_order()'s field names.
     *
     * @param array{billing: array<string, string>, shipping: array<string, string>} $details
     * @return array<string, mixed>
     */
    private function orderData(array $details): array
    {
        $data = ['payment_method' => '', 'ship_to_different_address' => true];
        foreach (['billing', 'shipping'] as $type) {
            foreach ($details[$type] as $field => $value) {
                $data["{$type}_{$field}"] = $value;
            }
        }

        return $data;
    }
}
