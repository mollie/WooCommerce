<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\ExpressComponent\Rules\AddressMapping;
use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingTaxRate;
use Mollie\WooCommerce\Payment\MolliePaymentAttempt;
use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\Payment\Rules\WebhookGuards;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use WC_Order;
use WC_Tax;
class ExpressOrderFactsBuilder
{
    private const SHIPPING_FIELDS = ['first_name', 'last_name', 'company', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'];
    public function __construct(private \Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionStore $store, private ProcessRecordStore $records)
    {
    }
    public function rememberedSession(): ?RememberedSession
    {
        $remembered = $this->store->remembered();
        if ($remembered === null) {
            return null;
        }
        return new RememberedSession($remembered['id'], $remembered['token'], $remembered['ref'], $remembered['fingerprint'], $remembered['expiresAt']);
    }
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
     * @param PaymentSnapshot|null $paidBy The payment a webhook brought: only then are the shipping tax rates read.
     */
    public function fromOrder(WC_Order $order, ?PaymentSnapshot $paidBy = null): ExpressOrderFacts
    {
        $tracked = MolliePaymentAttempt::paymentId($order);
        if ($tracked === '') {
            $tracked = (string) $order->get_transaction_id();
        }
        $record = $this->records->read($order);
        return new ExpressOrderFacts(orderId: $order->get_id(), expressRef: (string) $order->get_meta('_mollie_express_ref'), createdVia: $order->get_created_via(), total: $this->total($order), trackedPaymentId: $tracked !== '' ? $tracked : null, needsPayment: $order->needs_payment(), holdsShipping: $this->holdsShipping($order), needsShipping: $this->shipsItems($order), cancelledBy: $record->cancelledBy(), processed: $record->processed(), webhookNeedsPayment: $this->webhookNeedsPayment($order), cancelled: $order->has_status('cancelled'), holdsShippingLine: $order->get_items('shipping') !== [], shippingTaxRates: $paidBy === null ? [] : $this->shippingTaxRates($order, $paidBy));
    }
    /**
     * The rates the shipping option's price includes, found as WooCommerce finds them for shipping: the rates
     * of one tax class, for the address the option was priced for. Empty when shipping is not taxed there.
     *
     * @return list<ShippingTaxRate>
     */
    private function shippingTaxRates(WC_Order $order, PaymentSnapshot $paidBy): array
    {
        $taxClass = $this->shippingTaxClass($order);
        $location = $this->shippingTaxLocation($paidBy);
        if (!wc_tax_enabled() || $taxClass === null || $location === null) {
            return [];
        }
        $rates = [];
        foreach (WC_Tax::find_shipping_rates($location + ['tax_class' => $taxClass]) as $rateId => $rate) {
            $rates[] = new ShippingTaxRate((int) $rateId, (float) ($rate['rate'] ?? 0), ($rate['compound'] ?? 'no') === 'yes');
        }
        return $rates;
    }
    /**
     * The "Shipping tax class" setting; when it follows the items, the class of the order's taxable items,
     * the standard class first, then the first in the order of the settings. Null when no item is taxable.
     */
    private function shippingTaxClass(WC_Order $order): ?string
    {
        $setting = (string) get_option('woocommerce_shipping_tax_class');
        if ($setting !== 'inherit') {
            return $setting;
        }
        $found = array_intersect(array_merge([''], WC_Tax::get_tax_class_slugs()), $order->get_items_tax_classes());
        return $found === [] ? null : (string) reset($found);
    }
    /**
     * The shop's address when taxes are based on it, otherwise the address the wallet gave: the shipping
     * callback priced the option for it, as shipping and as billing address. Null when the payment has none.
     *
     * @return array{country: string, state: string, postcode: string, city: string}|null
     */
    private function shippingTaxLocation(PaymentSnapshot $paidBy): ?array
    {
        if (get_option('woocommerce_tax_based_on') === 'base') {
            $shop = WC()->countries;
            return ['country' => (string) $shop->get_base_country(), 'state' => (string) $shop->get_base_state(), 'postcode' => (string) $shop->get_base_postcode(), 'city' => (string) $shop->get_base_city()];
        }
        $shipTo = $paidBy->shippingAddress();
        $address = $shipTo === null ? [] : AddressMapping::toWooCommerce($shipTo->fields());
        if (($address['country'] ?? '') === '') {
            return null;
        }
        return ['country' => strtoupper($address['country']), 'state' => $address['state'] ?? '', 'postcode' => $address['postcode'] ?? '', 'city' => $address['city'] ?? ''];
    }
    private function webhookNeedsPayment(WC_Order $order): bool
    {
        return WebhookGuards::needsPayment((bool) $order->get_meta('_mollie_paid_by_other_gateway'), (bool) $order->get_meta('_mollie_paid_and_processed'), $order->get_meta('_mollie_authorized') === '1', $order->needs_payment(), \false);
    }
    public function total(WC_Order $order): ?Money
    {
        try {
            return \Mollie\WooCommerce\ExpressComponent\WooCommerce\WooCommerceAmount::toMoney($order->get_total('edit'), $order->get_currency());
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
            return \false;
        }
        foreach ($order->get_items() as $item) {
            $product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
            if ($product instanceof \WC_Product && $product->needs_shipping()) {
                return \true;
            }
        }
        return \false;
    }
    private function holdsShipping(WC_Order $order): bool
    {
        // WooCommerce may default the country, so it alone does not count.
        foreach (self::SHIPPING_FIELDS as $name) {
            $getter = [$order, "get_shipping_{$name}"];
            if ($name !== 'country' && is_callable($getter) && trim((string) $getter()) !== '') {
                return \true;
            }
        }
        return \false;
    }
}
