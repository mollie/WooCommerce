<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\PricedShippingOption;
use Mollie\WooCommerce\Shared\Values\Money;

/**
 * Mollie's shipping callback carries no cookie, so the store has no cart for it. The cart is saved when the
 * session is created, in a transient named after the hash of the session's secret, and read back when Mollie asks.
 * The address is Mollie's, one call at a time, and is never stored. What the last call offered is kept per option,
 * price and tax per rate, so the first webhook books the tax WooCommerce charged in the option the shopper paid.
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
     * A cart that ships nothing has no secret and nothing to save, bind or forget.
     *
     * @return bool Whether the cart was saved, or there was nothing to save.
     */
    public function save(string $secret, int $lifetimeSeconds): bool
    {
        if ($secret === '') {
            return true;
        }
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
            'expiresAt' => time() + $lifetimeSeconds,
            'priced' => [],
        ], $lifetimeSeconds);
    }

    /**
     * Binds the saved cart to the session Mollie created, for as long as that session lives.
     */
    public function attachSession(string $secret, string $sessionId, int $expiresAt): void
    {
        $saved = $secret === '' ? null : $this->read($secret);
        if ($saved === null) {
            return;
        }
        $saved['sessionId'] = $sessionId;
        $saved['expiresAt'] = max(time() + 60, $expiresAt + 60);
        $this->store($secret, $saved, $saved['expiresAt'] - time());
    }

    public function forget(string $secret): void
    {
        if ($secret === '') {
            return;
        }
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
     * @return array{rates: list<array{id: string, label: string, amount: Money, taxes: array<int, Money>}>, failed: bool}
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
            // Mollie sends no billing address: taxes that follow it would be those of no country.
            $customer->set_billing_location($address['country'], $address['state'], $address['postcode'], $address['city']);
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
     * Keeps what this call offered, replacing what an earlier call offered for another address.
     *
     * @param list<array{id: string, label: string, amount: Money, taxes: array<int, Money>}> $rates
     */
    public function rememberOffered(string $secret, array $rates): void
    {
        $saved = $this->read($secret);
        if ($saved === null || $saved['expiresAt'] <= time()) {
            return;
        }
        $priced = [];
        foreach ($rates as $rate) {
            $priced[$rate['id']] = [
                'gross' => $rate['amount']->minorUnits(),
                'currency' => $rate['amount']->currency(),
                'taxes' => array_map(static fn (Money $tax): int => $tax->minorUnits(), $rate['taxes']),
            ];
        }
        $saved['priced'] = $priced;
        $this->store($secret, $saved, $saved['expiresAt'] - time());
    }

    /**
     * The option as the last call offered it; null when that call offered no such option or the record is gone.
     */
    public function offered(string $secret, string $rateId): ?PricedShippingOption
    {
        $option = $this->read($secret)['priced'][$rateId] ?? null;
        if (!is_array($option)) {
            return null;
        }
        $currency = (string) $option['currency'];
        $taxes = [];
        foreach ($option['taxes'] as $taxRateId => $minor) {
            $taxes[(int) $taxRateId] = Money::fromMinorUnits((int) $minor, $currency);
        }

        return new PricedShippingOption($rateId, Money::fromMinorUnits((int) $option['gross'], $currency), $taxes);
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
     * @return list<array{id: string, label: string, amount: Money, taxes: array<int, Money>}>
     */
    private function rates(array $rates): array
    {
        $currency = get_woocommerce_currency();
        $priced = [];
        foreach ($rates as $id => $rate) {
            if (!$rate instanceof \WC_Shipping_Rate) {
                continue;
            }
            $taxes = [];
            foreach ($rate->get_taxes() as $taxRateId => $tax) {
                $taxes[(int) $taxRateId] = WooCommerceAmount::toMoney((float) $tax, $currency);
            }
            $priced[] = [
                'id' => (string) $id,
                'label' => (string) $rate->get_label(),
                'amount' => WooCommerceAmount::toMoney((float) $rate->get_cost() + array_sum($rate->get_taxes()), $currency),
                'taxes' => $taxes,
            ];
        }

        return $priced;
    }

    /**
     * @param array<string, mixed> $saved
     */
    private function store(string $secret, array $saved, int $lifetimeSeconds): bool
    {
        $name = $this->name($secret);

        // WordPress answers false when the value is already stored: an earlier attempt saved this same cart.
        return set_transient($name, $saved, $lifetimeSeconds) || get_transient($name) === $saved;
    }

    /**
     * @return array{hash: string, items: array<string, array<string, mixed>>, coupons: list<string>, sessionId: string, expiresAt: int, priced: array<string, array{gross: int, currency: string, taxes: array<int, int>}>}|null
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
        // Saved before the offered options were kept: no end is known, so nothing is added to it.
        $saved['expiresAt'] = (int) ($saved['expiresAt'] ?? 0);
        $saved['priced'] = is_array($saved['priced'] ?? null) ? $saved['priced'] : [];

        return $saved;
    }

    private function name(string $secret): string
    {
        return self::PREFIX . substr(hash('sha256', $secret), 0, 40);
    }
}
