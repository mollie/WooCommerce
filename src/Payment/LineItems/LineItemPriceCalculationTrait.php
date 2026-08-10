<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\LineItems;

use WC_Tax;

/**
 * Shared VAT-rate and Mollie price derivation for the Orders API (OrderLines) and
 * Payments API (PaymentLines) line-item builders.
 *
 * The vatRate is read from the configured WooCommerce tax rates and the gross price and VAT amount
 * are derived together from that single rate, so vatRate / totalAmount / vatAmount always satisfy
 * Mollie's cross-field validation (vatAmount == grossPrice * vatRate/(100+vatRate)) by construction.
 * Keeping this in one place prevents the two builders from drifting apart (PIWOO-931).
 */
trait LineItemPriceCalculationTrait
{
    /**
     * Calculate item tax percentage from the configured WooCommerce tax rates.
     *
     * @param  \WC_Order_Item        $cart_item Cart item.
     * @param  null|false|\WC_Product $product  Product object.
     *
     * @return int|float $item_vatRate Item tax percentage formatted for the Mollie API.
     */
    protected function get_item_vatRate($cart_item, $product)
    {
        if ($product && $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0) {
            // Calculate tax rate.
            $_tax = new WC_Tax();
            $tmp_rates = $_tax->get_rates($product->get_tax_class());
            $item_vatRate = 0;
            foreach ($tmp_rates as $rate) {
                if (isset($rate['rate'])) {
                    if ($rate['compound'] === "yes") {
                        $compoundRate = round($item_vatRate * ($rate['rate'] / 100)) + $rate['rate'];
                        $item_vatRate += $compoundRate;
                        continue;
                    }
                    $item_vatRate += $rate['rate'];
                }
            }
        } else {
            $item_vatRate = 0;
        }

        return $item_vatRate;
    }

    /**
     * Build gross price and VAT amount for the Mollie API from a single VAT rate.
     *
     * @param float $wcPrice
     * @param float $vatRate
     * @return float[]
     */
    protected function getMolliePrice(float $wcPrice, float $vatRate): array
    {
        $grossPrice = wc_prices_include_tax() ? $wcPrice : $wcPrice * (1 + ($vatRate / 100));

        return [
            'grossPrice' => $grossPrice,
            'vatAmount' => $grossPrice * ($vatRate / (100 + $vatRate)),
        ];
    }
}
