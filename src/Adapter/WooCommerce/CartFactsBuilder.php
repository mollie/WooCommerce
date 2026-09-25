<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WooCommerce;

use Mollie\WooCommerce\Core\Types\CartCoupon;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\CartFee;
use Mollie\WooCommerce\Core\Types\CartLine;
use Mollie\WooCommerce\Core\Types\CartShipping;
use Mollie\WooCommerce\Core\Types\Money;
use WC_Cart;
use WC_Customer;
/**
 * The only reader of the server-side cart and customer for the Express Component.
 *
 * Nothing the browser sends is read: the amount, the shipping rate and the destination are what
 * WooCommerce holds for this shopper's session, as the checkout form left it (REQ-B4). The cart is
 * loaded and its totals recalculated the way the Store API does, because a REST request does not
 * load either by itself.
 */
class CartFactsBuilder
{
    /**
     * Null when WooCommerce has no cart to give, which availability treats as an empty cart.
     */
    public function fromCart(): ?CartFacts
    {
        $cart = $this->cart();
        $customer = $this->customer();
        if ($cart === null || $customer === null) {
            return null;
        }
        $cart->calculate_totals();
        $currency = get_woocommerce_currency();
        $needsShipping = $cart->needs_shipping();
        $chosenRates = $needsShipping ? $this->chosenRates() : null;
        return new CartFacts(lines: $this->lines($cart, $customer, $currency), needsShipping: $needsShipping, shippingDestinationComplete: $customer->has_full_shipping_address(), shippingRateChosen: $chosenRates !== null, total: $this->money($cart->get_total('edit'), $currency), fees: $this->fees($cart, $customer, $currency), coupons: $this->coupons($cart, $currency), shipping: $chosenRates === null ? null : $this->shipping($cart, $customer, $chosenRates, $currency), cartHash: $cart->get_cart_hash(), destination: implode('|', [$customer->get_shipping_country(), $customer->get_shipping_state(), $customer->get_shipping_postcode(), $customer->get_shipping_city()]));
    }
    /**
     * @return list<CartLine>
     */
    private function lines(WC_Cart $cart, WC_Customer $customer, string $currency): array
    {
        $lines = [];
        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $lines[] = new CartLine(productId: (int) $product->get_id(), quantity: (int) $item['quantity'], isSubscription: $this->isSubscription($product), name: $product->get_name(), subtotal: $this->money((float) $item['line_subtotal'] + (float) $item['line_subtotal_tax'], $currency), vatRate: $product->is_taxable() ? $this->vatRate($product->get_tax_class(), $customer) : '0.00');
        }
        return $lines;
    }
    /**
     * @return list<CartFee>
     */
    private function fees(WC_Cart $cart, WC_Customer $customer, string $currency): array
    {
        $fees = [];
        foreach ($cart->get_fees() as $fee) {
            $fees[] = new CartFee((string) $fee->name, $this->money((float) $fee->total + (float) ($fee->tax ?? 0), $currency), !empty($fee->taxable) && (float) ($fee->tax ?? 0) !== 0.0 ? $this->vatRate((string) ($fee->tax_class ?? ''), $customer) : '0.00');
        }
        return $fees;
    }
    /**
     * @return list<CartCoupon>
     */
    private function coupons(WC_Cart $cart, string $currency): array
    {
        $taxes = $cart->get_coupon_discount_tax_totals();
        $coupons = [];
        foreach ($cart->get_coupon_discount_totals() as $code => $amount) {
            $coupons[] = new CartCoupon((string) $code, $this->money((float) $amount + (float) ($taxes[$code] ?? 0), $currency));
        }
        return $coupons;
    }
    /**
     * @param array<string, string> $chosenRates Rate id => label.
     */
    private function shipping(WC_Cart $cart, WC_Customer $customer, array $chosenRates, string $currency): CartShipping
    {
        $tax = (float) $cart->get_shipping_tax();
        return new CartShipping(array_keys($chosenRates), implode(', ', array_filter($chosenRates)), $this->money((float) $cart->get_shipping_total() + $tax, $currency), $tax !== 0.0 ? $this->sumOfRates(\WC_Tax::get_shipping_tax_rates(null, $customer)) : '0.00');
    }
    /**
     * The rate chosen for every shipping package, or null when a package has none among its rates.
     *
     * @return array<string, string>|null Rate id => label.
     */
    private function chosenRates(): ?array
    {
        $packages = WC()->shipping()->get_packages();
        /** @var \WC_Session|null $session WooCommerce leaves it null until the session is loaded. */
        $session = WC()->session;
        $chosen = $session instanceof \WC_Session ? (array) $session->get('chosen_shipping_methods', []) : [];
        if ($packages === []) {
            return null;
        }
        $rates = [];
        foreach ($packages as $index => $package) {
            $rateId = (string) ($chosen[$index] ?? '');
            $rate = $package['rates'][$rateId] ?? null;
            if ($rateId === '' || !$rate instanceof \WC_Shipping_Rate) {
                return null;
            }
            $rates[$rateId] = (string) $rate->get_label();
        }
        return $rates;
    }
    private function vatRate(string $taxClass, WC_Customer $customer): string
    {
        if (!wc_tax_enabled()) {
            return '0.00';
        }
        return $this->sumOfRates(\WC_Tax::get_rates($taxClass, $customer));
    }
    /**
     * @param array<int|string, array<string, mixed>> $rates
     */
    private function sumOfRates(array $rates): string
    {
        $sum = 0.0;
        foreach ($rates as $rate) {
            $sum += (float) ($rate['rate'] ?? 0);
        }
        return number_format($sum, 2, '.', '');
    }
    private function isSubscription(\WC_Product $product): bool
    {
        return class_exists('WC_Subscriptions_Product') && \WC_Subscriptions_Product::is_subscription($product);
    }
    /**
     * @param float|int|string $amount
     */
    private function money($amount, string $currency): Money
    {
        return \Mollie\WooCommerce\Adapter\WooCommerce\WooCommerceAmount::toMoney($amount, $currency);
    }
    private function cart(): ?WC_Cart
    {
        if (!function_exists('WC')) {
            return null;
        }
        /** @var WC_Cart|null $cart WooCommerce leaves it null until the cart is loaded. */
        $cart = WC()->cart;
        if (!$cart instanceof WC_Cart && did_action('woocommerce_init')) {
            wc_load_cart();
            $cart = WC()->cart;
        }
        return $cart instanceof WC_Cart ? $cart : null;
    }
    private function customer(): ?WC_Customer
    {
        return function_exists('WC') && WC()->customer instanceof WC_Customer ? WC()->customer : null;
    }
}
