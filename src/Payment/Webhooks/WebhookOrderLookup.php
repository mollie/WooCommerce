<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Webhooks;

use WC_Order;

/**
 * The indexed order lookups both webhook paths make for a Mollie id, in order: transaction_id, then
 * the Mollie order or payment meta. At most two orders, so an ambiguous id can be told apart from a
 * unique one. Reads only.
 */
final class WebhookOrderLookup
{
    /**
     * @return array<int, WC_Order>
     */
    public static function find(string $mollieId): array
    {
        $orders = wc_get_orders([
            'transaction_id' => $mollieId,
            'limit' => 2,
        ]);
        if ($orders) {
            return $orders;
        }

        return wc_get_orders([
            'limit' => 2,
            'meta_key' => substr($mollieId, 0, 4) === 'ord_' ? '_mollie_order_id' : '_mollie_payment_id',
            'meta_compare' => '=',
            'meta_value' => $mollieId,
        ]);
    }
}
