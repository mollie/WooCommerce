<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use RuntimeException;
use WC_Order;
class ExpressOrderFactory
{
    private \Mollie\WooCommerce\ExpressComponent\WooCommerce\PendingExpressOrders $pending;
    public function __construct(?\Mollie\WooCommerce\ExpressComponent\WooCommerce\PendingExpressOrders $pending = null)
    {
        $this->pending = $pending ?? new \Mollie\WooCommerce\ExpressComponent\WooCommerce\PendingExpressOrders();
    }
    /** Consumes the raised notices so they are not shown again on the next page. */
    public function invalidCartReason(): ?string
    {
        $before = wc_get_notices('error');
        $valid = WC()->cart->check_cart_items();
        $raised = array_slice(wc_get_notices('error'), count($before));
        if ($valid !== \false && $raised === []) {
            return null;
        }
        wc_set_notices(array_merge(wc_get_notices(), ['error' => $before]));
        $first = $raised[0] ?? null;
        $text = is_array($first) ? (string) ($first['notice'] ?? '') : (string) $first;
        $text = trim(html_entity_decode(wp_strip_all_tags($text), \ENT_QUOTES, 'UTF-8'));
        return $text !== '' ? $text : __('Your cart cannot be ordered as it is.', 'mollie-payments-for-woocommerce');
    }
    /**
     * @param array{billing: array<string, string>, shipping: array<string, string>} $details
     * @throws RuntimeException When WooCommerce did not create the order; nothing is left behind.
     */
    public function create(array $details): WC_Order
    {
        $session = WC()->session;
        $this->releaseStockHeldByShoppersPendingOrder($session);
        $created = null;
        $capture = static function (WC_Order $order) use (&$created): void {
            $created = $order;
        };
        add_action('woocommerce_checkout_create_order', $capture, \PHP_INT_MIN, 1);
        // create_order() would otherwise resume another checkout's order awaiting payment.
        $awaiting = $session->get('order_awaiting_payment');
        $session->set('order_awaiting_payment', null);
        try {
            $orderId = WC()->checkout()->create_order($this->orderData($details));
        } catch (\Throwable $error) {
            $orderId = null;
        } finally {
            remove_action('woocommerce_checkout_create_order', $capture, \PHP_INT_MIN);
            $session->set('order_awaiting_payment', $awaiting);
        }
        $order = is_int($orderId) && $orderId > 0 ? wc_get_order($orderId) : null;
        if (!$order instanceof WC_Order) {
            if ($created instanceof WC_Order && $created->get_id() > 0) {
                $this->delete($created);
            }
            throw new RuntimeException('WooCommerce did not create the express order.');
        }
        // Keeps cleanup scheduled.
        $this->pending->remember();
        return $order;
    }
    public function delete(WC_Order $order): void
    {
        $order->delete(\true);
    }
    private function releaseStockHeldByShoppersPendingOrder(\WC_Session $session): void
    {
        if (!function_exists('wc_release_stock_for_order')) {
            return;
        }
        $order = $this->shoppersOwnOrder($session);
        if ($order !== null && $order->has_status('pending')) {
            wc_release_stock_for_order($order);
        }
    }
    /** As WC_Cart::check_cart_item_stock() reads it. */
    private function shoppersOwnOrder(\WC_Session $session): ?WC_Order
    {
        $orderId = absint($session->get('order_awaiting_payment')) ?: absint($session->get('store_api_draft_order', 0));
        $order = $orderId > 0 ? wc_get_order($orderId) : null;
        return $order instanceof WC_Order ? $order : null;
    }
    /**
     * @param array{billing: array<string, string>, shipping: array<string, string>} $details
     * @return array<string, mixed>
     */
    private function orderData(array $details): array
    {
        $data = ['payment_method' => '', 'ship_to_different_address' => \true];
        foreach (['billing', 'shipping'] as $type) {
            foreach ($details[$type] as $field => $value) {
                $data["{$type}_{$field}"] = $value;
            }
        }
        return $data;
    }
}
