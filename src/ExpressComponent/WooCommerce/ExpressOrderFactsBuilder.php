<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingTaxRate;
use Mollie\WooCommerce\Payment\MolliePaymentAttempt;
use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\Payment\Rules\WebhookGuards;
use Mollie\WooCommerce\Shared\Values\Money;
use WC_Order;
use WC_Tax;

class ExpressOrderFactsBuilder
{
    private const SHIPPING_FIELDS = [
        'first_name', 'last_name', 'company', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country',
    ];

    public function __construct(private ExpressSessionStore $store, private ProcessRecordStore $records)
    {
    }

    public function rememberedSession(): ?RememberedSession
    {
        $remembered = $this->store->remembered();
        if ($remembered === null) {
            return null;
        }

        return new RememberedSession(
            $remembered['id'],
            $remembered['token'],
            $remembered['ref'],
            $remembered['fingerprint'],
            $remembered['expiresAt']
        );
    }

    public function orderByRef(string $ref): ?WC_Order
    {
        if ($ref === '') {
            return null;
        }
        $orders = wc_get_orders([
            'limit' => 1,
            'type' => 'shop_order',
            'status' => array_keys(wc_get_order_statuses()),
            'meta_key' => '_mollie_express_ref',
            'meta_value' => $ref,
        ]);
        $order = $orders[0] ?? null;
        if (!$order instanceof WC_Order || !hash_equals((string) $order->get_meta('_mollie_express_ref'), $ref)) {
            return null;
        }

        return $order;
    }

    public function fromOrder(WC_Order $order): ExpressOrderFacts
    {
        $tracked = MolliePaymentAttempt::paymentId($order);
        if ($tracked === '') {
            $tracked = (string) $order->get_transaction_id();
        }

        $record = $this->records->read($order);

        return new ExpressOrderFacts(
            orderId: $order->get_id(),
            expressRef: (string) $order->get_meta('_mollie_express_ref'),
            createdVia: $order->get_created_via(),
            total: $this->total($order),
            trackedPaymentId: $tracked !== '' ? $tracked : null,
            needsPayment: $order->needs_payment(),
            holdsShipping: $this->holdsShipping($order),
            needsShipping: $this->shipsItems($order),
            cancelledBy: $record->cancelledBy(),
            processed: $record->processed(),
            webhookNeedsPayment: $this->webhookNeedsPayment($order),
            cancelled: $order->has_status('cancelled'),
            holdsShippingLine: $order->get_items('shipping') !== [],
            shippingTaxRates: $this->shippingTaxRates($order)
        );
    }

    /**
     * The order's own tax lines say which rates applied to it; those that also tax shipping are the rates the
     * shipping option's price includes, in WooCommerce's order: by priority, then by rate order. Empty when
     * none does.
     *
     * @return list<ShippingTaxRate>
     */
    private function shippingTaxRates(WC_Order $order): array
    {
        $rows = [];
        foreach ($order->get_taxes() as $tax) {
            $row = WC_Tax::_get_tax_rate($tax->get_rate_id(), OBJECT);
            if (is_object($row) && (int) $row->tax_rate_shipping === 1) {
                $rows[] = $row;
            }
        }
        usort(
            $rows,
            static fn (object $a, object $b): int => [(int) $a->tax_rate_priority, (int) $a->tax_rate_order]
                <=> [(int) $b->tax_rate_priority, (int) $b->tax_rate_order]
        );

        return array_map(
            static fn (object $row): ShippingTaxRate => new ShippingTaxRate(
                (int) $row->tax_rate_id,
                (float) $row->tax_rate,
                (int) $row->tax_rate_compound === 1
            ),
            $rows
        );
    }

    private function webhookNeedsPayment(WC_Order $order): bool
    {
        return WebhookGuards::needsPayment(
            (bool) $order->get_meta('_mollie_paid_by_other_gateway'),
            (bool) $order->get_meta('_mollie_paid_and_processed'),
            $order->get_meta('_mollie_authorized') === '1',
            $order->needs_payment(),
            false // on-hold initial status: no wallet delays confirmation
        );
    }

    public function total(WC_Order $order): ?Money
    {
        try {
            return WooCommerceAmount::toMoney($order->get_total('edit'), $order->get_currency());
        } catch (InvalidArgumentException $unusable) {
            return null;
        }
    }

    /**
     * @return list<WC_Order>
     */
    public function abandonCandidates(int $cutoff, int $limit): array
    {
        $orders = wc_get_orders([
            'limit' => $limit,
            'type' => 'shop_order',
            'status' => ['pending'],
            'orderby' => 'date',
            'order' => 'ASC',
            // Not meta_query: the posts order storage ignores it.
            'meta_key' => '_mollie_express_expires_at',
            'meta_value' => $cutoff,
            'meta_compare' => '<',
            'meta_type' => 'NUMERIC',
        ]);

        return array_values(array_filter($orders, static function ($order): bool {
            return $order instanceof WC_Order && $order->get_created_via() === StartOrderDecision::CREATED_VIA;
        }));
    }

    /**
     * By its items, not by WC_Order::needs_shipping_address(): that one asks for a shipping line, and the
     * express order has none until the first webhook adds the option the shopper chose.
     */
    private function shipsItems(WC_Order $order): bool
    {
        if (!wc_shipping_enabled()) {
            return false;
        }
        foreach ($order->get_items() as $item) {
            $product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
            if ($product instanceof \WC_Product && $product->needs_shipping()) {
                return true;
            }
        }

        return false;
    }

    private function holdsShipping(WC_Order $order): bool
    {
        // WooCommerce may default the country, so it alone does not count.
        foreach (self::SHIPPING_FIELDS as $name) {
            $getter = [$order, "get_shipping_{$name}"];
            if ($name !== 'country' && is_callable($getter) && trim((string) $getter()) !== '') {
                return true;
            }
        }

        return false;
    }
}
