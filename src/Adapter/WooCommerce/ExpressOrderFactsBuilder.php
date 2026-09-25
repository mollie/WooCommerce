<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\Core\Express\StartOrderDecision;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerce\Payment\MolliePaymentAttempt;
use WC_Customer;
use WC_Order;
/**
 * Reads what the express order decisions need from WooCommerce: the shopper's remembered session,
 * the order that carries an express_ref, the details the store holds for this shopper, and the
 * orders cleanup may look at. Reads only.
 */
class ExpressOrderFactsBuilder
{
    private const BILLING_FIELDS = ['first_name', 'last_name', 'company', 'email', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'];
    private const SHIPPING_FIELDS = ['first_name', 'last_name', 'company', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'];
    public function __construct(private \Mollie\WooCommerce\Adapter\WooCommerce\ExpressSessionStore $store)
    {
    }
    /**
     * The remembered session only; the order carrying its ref is looked up by withExistingOrder(),
     * under the lock.
     */
    public function fromStore(): ExpressOrderFacts
    {
        $remembered = $this->store->remembered();
        if ($remembered === null) {
            return new ExpressOrderFacts();
        }
        return new ExpressOrderFacts(sessionId: $remembered['id'], expressRef: $remembered['ref'], fingerprint: $remembered['fingerprint'], expiresAt: $remembered['expiresAt']);
    }
    public function withExistingOrder(ExpressOrderFacts $facts): ExpressOrderFacts
    {
        $order = $facts->expressRef() === null ? null : $this->orderByRef($facts->expressRef());
        return new ExpressOrderFacts(sessionId: $facts->sessionId(), expressRef: $facts->expressRef(), fingerprint: $facts->fingerprint(), expiresAt: $facts->expiresAt(), existingOrderId: $order?->get_id());
    }
    /**
     * The express order carrying this ref, compared in constant time against what the order stores.
     */
    public function orderByRef(string $ref): ?WC_Order
    {
        if ($ref === '') {
            return null;
        }
        $orders = wc_get_orders(['limit' => 1, 'type' => 'shop_order', 'status' => array_keys(wc_get_order_statuses()), 'meta_key' => '_mollie_express_ref', 'meta_value' => $ref]);
        $order = $orders[0] ?? null;
        if (!$order instanceof WC_Order || !hash_equals((string) $order->get_meta('_mollie_express_ref'), $ref)) {
            return null;
        }
        return $order;
    }
    /**
     * The order that carries a payment's express_ref, as the webhook's match needs it. An address
     * type is held when any of its fields but the country is filled; WooCommerce may default the
     * country on its own.
     */
    public function forResolution(WC_Order $order): ExpressOrderFacts
    {
        $tracked = MolliePaymentAttempt::paymentId($order);
        if ($tracked === '') {
            $tracked = (string) $order->get_transaction_id();
        }
        return new ExpressOrderFacts(expressRef: (string) $order->get_meta('_mollie_express_ref'), existingOrderId: $order->get_id(), createdVia: $order->get_created_via(), total: $this->total($order), trackedPaymentId: $tracked !== '' ? $tracked : null, needsPayment: $order->needs_payment(), holdsBilling: $this->holds($order, 'billing', self::BILLING_FIELDS), holdsShipping: $this->holds($order, 'shipping', self::SHIPPING_FIELDS), needsShipping: $order->needs_shipping_address());
    }
    /**
     * What the store holds for this shopper: the checkout form as WooCommerce keeps it on the
     * customer session for a guest, the account (overlaid by the form) for a logged-in shopper.
     *
     * @return array{billing: array<string, string>, shipping: array<string, string>}
     */
    public function shopperDetails(): array
    {
        $customer = function_exists('WC') && WC()->customer instanceof WC_Customer ? WC()->customer : null;
        if ($customer === null) {
            return ['billing' => [], 'shipping' => []];
        }
        return ['billing' => $this->fields($customer, 'billing', self::BILLING_FIELDS), 'shipping' => $this->fields($customer, 'shipping', self::SHIPPING_FIELDS)];
    }
    /**
     * Pending express orders whose session expired before the cutoff, oldest first.
     *
     * @return list<WC_Order>
     */
    public function abandonCandidates(int $cutoff, int $limit): array
    {
        $orders = wc_get_orders(['limit' => $limit, 'type' => 'shop_order', 'status' => ['pending'], 'orderby' => 'date', 'order' => 'ASC', 'meta_query' => [['key' => '_mollie_express_expires_at', 'value' => $cutoff, 'compare' => '<', 'type' => 'NUMERIC']]]);
        return array_values(array_filter($orders, static function ($order): bool {
            return $order instanceof WC_Order && $order->get_created_via() === StartOrderDecision::CREATED_VIA;
        }));
    }
    /**
     * The order total with the precision of its currency, as Mollie was asked for it.
     */
    private function total(WC_Order $order): ?Money
    {
        $currency = $order->get_currency();
        $amount = (float) $order->get_total('edit');
        try {
            return Money::fromDecimal(number_format($amount, 2, '.', ''), $currency);
        } catch (InvalidArgumentException $exception) {
            try {
                // A currency without decimals.
                return Money::fromDecimal(number_format($amount, 0, '.', ''), $currency);
            } catch (InvalidArgumentException $unusable) {
                return null;
            }
        }
    }
    /**
     * @param list<string> $names
     */
    private function holds(WC_Order $order, string $type, array $names): bool
    {
        foreach ($names as $name) {
            $getter = [$order, "get_{$type}_{$name}"];
            if ($name !== 'country' && is_callable($getter) && trim((string) $getter()) !== '') {
                return \true;
            }
        }
        return \false;
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
