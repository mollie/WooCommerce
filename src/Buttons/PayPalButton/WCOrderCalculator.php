<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\PayPalButton;

use WC_Order;
use WC_Order_Item_Product;
class WCOrderCalculator extends WC_Order
{
    /**
     * Calculate totals by looking at the contents of the order.
     *
     * @since 2.2
     * @param  bool $and_taxes Calc taxes if true.
     * @return float calculated grand total.
     */
    public function calculate_totals($and_taxes = \true)
    {
        do_action('woocommerce_order_before_calculate_totals', $and_taxes, $this);
        $fees_total = 0;
        $shipping_total = 0;
        $cart_subtotal_tax = 0;
        $cart_total_tax = 0;
        $cart_subtotal = $this->get_cart_subtotal_for_order();
        $cart_total = $this->get_cart_total_for_order();
        // Sum shipping costs.
        foreach ($this->get_shipping_methods() as $shipping) {
            $shipping_total += $this->round($shipping->get_total(), wc_get_price_decimals());
        }
        $this->set_shipping_total($shipping_total);
        // Sum fee costs.
        foreach ($this->get_fees() as $item) {
            $fee_total = $item->get_total();
            if (0 > $fee_total) {
                $max_discount = $this->round($cart_total + $fees_total + $shipping_total, wc_get_price_decimals()) * -1;
                if ($fee_total < $max_discount && 0 > $max_discount) {
                    $item->set_total($max_discount);
                }
            }
            $fees_total += $item->get_total();
        }
        // Calculate taxes for items, shipping, discounts. Note; this also triggers save().
        if ($and_taxes) {
            $this->calculate_taxes();
        }
        // Sum taxes again so we can work out how much tax was discounted. This uses original values, not those possibly rounded to 2dp.
        foreach ($this->get_items() as $item) {
            $taxes = $item->get_taxes();
            foreach ($taxes['total'] as $tax_rate_id => $tax) {
                $cart_total_tax += (float) $tax;
            }
            foreach ($taxes['subtotal'] as $tax_rate_id => $tax) {
                $cart_subtotal_tax += (float) $tax;
            }
        }
        $this->set_discount_total($this->round($cart_subtotal - $cart_total, wc_get_price_decimals()));
        $this->set_discount_tax(wc_round_tax_total($cart_subtotal_tax - $cart_total_tax));
        $this->set_total($this->round($cart_total + $fees_total + $this->get_shipping_total() + $this->get_cart_tax() + $this->get_shipping_tax(), wc_get_price_decimals()));
        do_action('woocommerce_order_after_calculate_totals', $and_taxes, $this);
        return $this->get_total();
    }
    /**
     * Update tax lines for the order based on the line item taxes themselves.
     */
    public function update_taxes()
    {
        $cart_taxes = [];
        $shipping_taxes = [];
        $existing_taxes = $this->get_taxes();
        $saved_rate_ids = [];
        foreach ($this->get_items(['line_item', 'fee']) as $item_id => $item) {
            $taxes = $item->get_taxes();
            foreach ($taxes['total'] as $tax_rate_id => $tax) {
                $tax_amount = $this->round_line_tax($tax, \false);
                $cart_taxes[$tax_rate_id] = isset($cart_taxes[$tax_rate_id]) ? $cart_taxes[$tax_rate_id] + $tax_amount : $tax_amount;
            }
        }
        foreach ($this->get_shipping_methods() as $item_id => $item) {
            $taxes = $item->get_taxes();
            foreach ($taxes['total'] as $tax_rate_id => $tax) {
                $tax_amount = (float) $tax;
                if ('yes' !== get_option('woocommerce_tax_round_at_subtotal')) {
                    $tax_amount = wc_round_tax_total($tax_amount);
                }
                $shipping_taxes[$tax_rate_id] = isset($shipping_taxes[$tax_rate_id]) ? $shipping_taxes[$tax_rate_id] + $tax_amount : $tax_amount;
            }
        }
        foreach ($existing_taxes as $tax) {
            // Remove taxes which no longer exist for cart/shipping.
            if (!array_key_exists($tax->get_rate_id(), $cart_taxes) && !array_key_exists($tax->get_rate_id(), $shipping_taxes) || in_array($tax->get_rate_id(), $saved_rate_ids, \true)) {
                $this->remove_item($tax->get_id());
                continue;
            }
            $saved_rate_ids[] = $tax->get_rate_id();
            $tax->set_tax_total(isset($cart_taxes[$tax->get_rate_id()]) ? $cart_taxes[$tax->get_rate_id()] : 0);
            $tax->set_shipping_tax_total(!empty($shipping_taxes[$tax->get_rate_id()]) ? $shipping_taxes[$tax->get_rate_id()] : 0);
            $tax->save();
        }
        $new_rate_ids = wp_parse_id_list(array_diff(array_keys($cart_taxes + $shipping_taxes), $saved_rate_ids));
        // New taxes.
        foreach ($new_rate_ids as $tax_rate_id) {
            $item = new \WC_Order_Item_Tax();
            $item->set_rate($tax_rate_id);
            $item->set_tax_total(isset($cart_taxes[$tax_rate_id]) ? $cart_taxes[$tax_rate_id] : 0);
            $item->set_shipping_tax_total(!empty($shipping_taxes[$tax_rate_id]) ? $shipping_taxes[$tax_rate_id] : 0);
            $this->add_item($item);
        }
        $this->set_shipping_tax(array_sum($shipping_taxes));
        $this->set_cart_tax(array_sum($cart_taxes));
    }
    public function add_product($product, $qty = 1, $args = [])
    {
        if ($product) {
            $default_args = ['name' => $product->get_name(), 'tax_class' => $product->get_tax_class(), 'product_id' => $product->is_type('variation') ? $product->get_parent_id() : $product->get_id(), 'variation_id' => $product->is_type('variation') ? $product->get_id() : 0, 'variation' => $product->is_type('variation') ? $product->get_attributes() : [], 'subtotal' => wc_get_price_excluding_tax($product, ['qty' => $qty]), 'total' => wc_get_price_excluding_tax($product, ['qty' => $qty]), 'quantity' => $qty];
        } else {
            $default_args = ['quantity' => $qty];
        }
        $args = wp_parse_args($args, $default_args);
        // BW compatibility with old args.
        if (isset($args['totals'])) {
            foreach ($args['totals'] as $key => $value) {
                if ('tax' === $key) {
                    $args['total_tax'] = $value;
                } elseif ('tax_data' === $key) {
                    $args['taxes'] = $value;
                } else {
                    $args[$key] = $value;
                }
            }
        }
        $item = new \WC_Order_Item_Product();
        $item->set_props($args);
        $item->set_backorder_meta();
        $item->set_order_id($this->get_id());
        $this->add_item($item);
        wc_do_deprecated_action('woocommerce_order_add_product', [$this->get_id(), $item->get_id(), $product, $qty, $args], '3.0', 'woocommerce_new_order_item action instead');
        delete_transient('wc_order_' . $this->get_id() . '_needs_processing');
        return $item->get_id();
    }
    protected function round($val, $precision = 0, $mode = \PHP_ROUND_HALF_UP)
    {
        if (!is_numeric($val)) {
            $val = floatval($val);
        }
        return round($val, $precision, $mode);
    }
}
