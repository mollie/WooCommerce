<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\Payment\MolliePaymentAttempt;
use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\Payment\Rules\WebhookGuards;
use Mollie\WooCommerce\Shared\Values\Money;
use WC_Customer;
use WC_Order;

class ExpressOrderFactsBuilder
{
    private const BILLING_FIELDS = [
        'first_name', 'last_name', 'company', 'email', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state',
        'country',
    ];

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
            needsShipping: $order->needs_shipping_address(),
            cancelledBy: $record->cancelledBy(),
            processed: $record->processed(),
            webhookNeedsPayment: $this->webhookNeedsPayment($order),
            cancelled: $order->has_status('cancelled')
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
     * @return array{billing: array<string, string>, shipping: array<string, string>}
     */
    public function shopperDetails(): array
    {
        $customer = function_exists('WC') && WC()->customer instanceof WC_Customer ? WC()->customer : null;
        if ($customer === null) {
            return ['billing' => [], 'shipping' => []];
        }

        return [
            'billing' => $this->fields($customer, 'billing', self::BILLING_FIELDS),
            'shipping' => $this->fields($customer, 'shipping', self::SHIPPING_FIELDS),
        ];
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
            'meta_query' => [
                [
                    'key' => '_mollie_express_expires_at',
                    'value' => $cutoff,
                    'compare' => '<',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        return array_values(array_filter($orders, static function ($order): bool {
            return $order instanceof WC_Order && $order->get_created_via() === StartOrderDecision::CREATED_VIA;
        }));
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

    /**
     * @param list<string> $names
     * @return array<string, string>
     */
    private function fields(WC_Customer $customer, string $type, array $names): array
    {
        $fields = [];
        foreach ($names as $name) {
            $getter = [$customer, "get_{$type}_{$name}"];
            if (is_callable($getter)) {
                $fields[$name] = (string) $getter();
            }
        }

        return $fields;
    }
}
