<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartCoupon;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFee;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartShipping;
use Mollie\WooCommerce\Shared\Values\Money;
use WC_Cart;
use WC_Customer;
/**
 * Reads the server-side cart. A REST request loads neither cart nor totals, so both are loaded here.
 */
class CartFactsBuilder
{
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
        [$taxEnabled, $taxBasedOn, $countries, $ratesVary] = $this->taxFacts($needsShipping);
        return new CartFacts(lines: $this->lines($cart, $customer, $currency), needsShipping: $needsShipping, shippingDestinationComplete: $customer->has_full_shipping_address(), shippingRateChosen: $chosenRates !== null, total: $this->money($cart->get_total('edit'), $currency), fees: $this->fees($cart, $customer, $currency), coupons: $this->coupons($cart, $currency), shipping: $chosenRates === null ? null : $this->shipping($cart, $customer, $chosenRates, $currency), cartHash: $cart->get_cart_hash(), destination: implode('|', [$customer->get_shipping_country(), $customer->get_shipping_state(), $customer->get_shipping_postcode(), $customer->get_shipping_city()]), taxEnabled: $taxEnabled, taxBasedOn: $taxBasedOn, taxCountries: $countries, ratesVaryWithinCountry: $ratesVary);
    }
    /**
     * The verdict for the merchant notice, read from the shop's settings alone: no cart is needed.
     */
    public function taxVerdict(): string
    {
        [$taxEnabled, $taxBasedOn, $countries, $ratesVary] = $this->taxFacts(\true);
        return TaxCompatibility::decide($taxEnabled, $taxBasedOn, $countries, $ratesVary);
    }
    /**
     * @return array{0: bool, 1: string, 2: list<string>, 3: bool}
     */
    private function taxFacts(bool $cartShips): array
    {
        $taxEnabled = wc_tax_enabled();
        $taxBasedOn = $this->taxBasedOn();
        $countries = $this->taxCountries($cartShips);
        return [$taxEnabled, $taxBasedOn, $countries, $this->ratesVaryWithinCountry($taxEnabled, $taxBasedOn, $countries)];
    }
    private function taxBasedOn(): string
    {
        return (string) get_option('woocommerce_tax_based_on', 'shipping');
    }
    /**
     * The countries whose address the taxes can follow: where the shop ships to when the taxes follow the
     * shipping address of a cart that ships, where it sells to otherwise. Empty when WooCommerce does not
     * answer: the rule then counts the shop as incompatible.
     *
     * @return list<string>
     */
    private function taxCountries(bool $cartShips): array
    {
        if (!function_exists('WC') || !WC()->countries instanceof \WC_Countries) {
            return [];
        }
        $followsShipping = $cartShips && $this->taxBasedOn() === 'shipping' && get_option('woocommerce_ship_to_countries') !== 'disabled';
        return array_keys($followsShipping ? WC()->countries->get_shipping_countries() : WC()->countries->get_allowed_countries());
    }
    /**
     * A rate of the only country the taxes can follow (or of any country) that names a state, a postcode or a city.
     * The rate table is read only when the taxes follow the customer's address: otherwise the answer cannot matter.
     *
     * @param list<string> $countries
     */
    private function ratesVaryWithinCountry(bool $taxEnabled, string $taxBasedOn, array $countries): bool
    {
        if (!TaxCompatibility::followsCustomerAddress($taxEnabled, $taxBasedOn) || count($countries) !== 1) {
            return \false;
        }
        foreach (array_merge([''], \WC_Tax::get_tax_class_slugs()) as $taxClass) {
            foreach (\WC_Tax::get_rates_for_tax_class($taxClass) as $rate) {
                if (!in_array((string) $rate->tax_rate_country, ['', $countries[0]], \true)) {
                    continue;
                }
                if ((string) $rate->tax_rate_state !== '' || (int) $rate->postcode_count > 0 || (int) $rate->city_count > 0) {
                    return \true;
                }
            }
        }
        return \false;
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
     * Null when any package has no chosen rate.
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
        return \Mollie\WooCommerce\ExpressComponent\WooCommerce\WooCommerceAmount::toMoney($amount, $currency);
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
