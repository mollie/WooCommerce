<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\Shared\Values\Money;

/**
 * Mollie's shipping callback carries no cookie, so the store has no cart for it. The cart is saved when the
 * session is created, in a transient named after the hash of the session's secret, and read back when Mollie asks.
 * Only the cart is kept: the address is Mollie's, one call at a time, and is never stored.
 */
class ExpressShippingSnapshot
{
    private const PREFIX = 'mollie_express_shipping_';

    /**
     * Derived, not random: a retry of the session call sends the same body under the same idempotency key.
     * The expressRef is itself unguessable and per session.
     */
    public function secretFor(string $expressRef): string
    {
        return substr(hash_hmac('sha256', 'shipping|' . $expressRef, wp_salt('auth')), 0, 32);
    }

    /**
     * @return bool Whether a cart that ships was saved.
     */
    public function save(string $secret, int $lifetimeSeconds): bool
    {
        $cart = function_exists('WC') ? WC()->cart : null;
        if (!$cart instanceof \WC_Cart) {
            return false;
        }
        $items = [];
        foreach ($cart->get_cart() as $key => $item) {
            // As WooCommerce keeps a cart in the session: the product object is read again on the way back.
            unset($item['data']);
            $items[(string) $key] = $item;
        }

        return $this->store($secret, [
            'hash' => hash('sha256', $secret),
            'items' => $items,
            'coupons' => array_values(array_map('strval', $cart->get_applied_coupons())),
            'sessionId' => '',
        ], $lifetimeSeconds);
    }

    /**
     * Binds the saved cart to the session Mollie created, for as long as that session lives.
     */
    public function attachSession(string $secret, string $sessionId, int $expiresAt): void
    {
        $saved = $this->read($secret);
        if ($saved === null) {
            return;
        }
        $saved['sessionId'] = $sessionId;
        $this->store($secret, $saved, max(60, $expiresAt - time() + 60));
    }

    public function forget(string $secret): void
    {
        delete_transient($this->name($secret));
    }

    /** False for a secret that was never saved, has expired, or is wrong. */
    public function exists(string $secret): bool
    {
        return $this->read($secret) !== null;
    }

    /** Whether the cart saved under this secret was saved for this Mollie session. */
    public function isBoundTo(string $secret, string $sessionId): bool
    {
        $saved = $this->read($secret);

        return $saved !== null && $saved['sessionId'] !== '' && $sessionId !== '' && hash_equals($saved['sessionId'], $sessionId);
    }

    /**
     * The rates WooCommerce charges the saved cart for the address, VAT included, in WooCommerce's order.
     * The cart is loaded into this request's own cart, so free shipping, coupons and the plugins that hook the
     * cart see what they would see at checkout; it is emptied again and never saved.
     *
     * @param array{country: string, state: string, postcode: string, city: string} $address
     * @return array{rates: list<array{id: string, label: string, amount: Money}>, failed: bool}
     */
    public function ratesFor(string $secret, array $address): array
    {
        $saved = $this->read($secret);
        $cart = function_exists('WC') ? WC()->cart : null;
        $customer = function_exists('WC') ? WC()->customer : null;
        if ($saved === null || !$cart instanceof \WC_Cart || !$customer instanceof \WC_Customer) {
            return ['rates' => [], 'failed' => true];
        }
        $contents = $this->contents($saved['items']);
        if ($contents === null) {
            return ['rates' => [], 'failed' => true];
        }

        // WooCommerce saves a session only for a request that carries its cookie; Mollie's call carries none.
        try {
            $cart->empty_cart(false);
            $cart->set_cart_contents($contents);
            $cart->set_applied_coupons($saved['coupons']);
            $customer->set_shipping_location($address['country'], $address['state'], $address['postcode'], $address['city']);
            $cart->calculate_totals();
            $packages = WC()->shipping()->get_packages();

            // Mollie takes one list of options for the whole order: several shipments have no single price.
            if (count($packages) !== 1) {
                return ['rates' => [], 'failed' => true];
            }

            return ['rates' => $this->rates($packages[array_key_first($packages)]['rates'] ?? []), 'failed' => false];
        } catch (\Throwable) {
            return ['rates' => [], 'failed' => true];
        } finally {
            $cart->empty_cart(false);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>|null Null when a product can no longer be read: that price cannot be computed.
     */
    private function contents(array $items): ?array
    {
        $contents = [];
        foreach ($items as $key => $values) {
            $product = wc_get_product((int) ($values['variation_id'] ?? 0) ?: (int) ($values['product_id'] ?? 0));
            if (!$product instanceof \WC_Product) {
                return null;
            }
            $values['data'] = $product;
            $contents[$key] = apply_filters('woocommerce_get_cart_item_from_session', $values, $values, $key);
        }

        return $contents;
    }

    /**
     * @param array<int|string, mixed> $rates
     * @return list<array{id: string, label: string, amount: Money}>
     */
    private function rates(array $rates): array
    {
        $currency = get_woocommerce_currency();
        $priced = [];
        foreach ($rates as $id => $rate) {
            if (!$rate instanceof \WC_Shipping_Rate) {
                continue;
            }
            $priced[] = [
                'id' => (string) $id,
                'label' => (string) $rate->get_label(),
                'amount' => WooCommerceAmount::toMoney((float) $rate->get_cost() + array_sum($rate->get_taxes()), $currency),
            ];
        }

        return $priced;
    }

    /**
     * @param array<string, mixed> $saved
     */
    private function store(string $secret, array $saved, int $lifetimeSeconds): bool
    {
        return set_transient($this->name($secret), $saved, $lifetimeSeconds);
    }

    /**
     * @return array{hash: string, items: array<string, array<string, mixed>>, coupons: list<string>, sessionId: string}|null
     */
    private function read(string $secret): ?array
    {
        if ($secret === '') {
            return null;
        }
        $saved = get_transient($this->name($secret));
        if (!is_array($saved) || !isset($saved['hash'], $saved['items'], $saved['coupons'], $saved['sessionId'])) {
            return null;
        }
        if (!hash_equals((string) $saved['hash'], hash('sha256', $secret))) {
            return null;
        }

        return $saved;
    }

    private function name(string $secret): string
    {
        return self::PREFIX . substr(hash('sha256', $secret), 0, 40);
    }
}
