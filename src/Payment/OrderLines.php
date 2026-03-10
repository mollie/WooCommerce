<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerce\Shared\Data;
use WC_Order;
use WC_Order_Item;
use WC_Tax;
class OrderLines
{
    /**
     * Formatted order lines.
     *
     * @var $order_lines
     */
    private $order_lines = [];
    /**
     * WooCommerce order.
     *
     * @var WC_Order
     */
    private $order;
    /**
     * WooCommerce currency.
     *
     */
    private $currency;
    /**
     * @var Data
     */
    protected $dataHelper;
    protected $pluginId;
    /**
     * Mollie_WC_Helper_Order_Lines constructor.
     *
     *
     */
    public function __construct(Data $dataHelper, string $pluginId)
    {
        $this->dataHelper = $dataHelper;
        $this->pluginId = $pluginId;
    }
    /**
     * Gets formatted order lines from WooCommerce order.
     *
     * @param WC_Order $order WooCommerce Order
     *
     * @return array
     */
    public function order_lines($order)
    {
        $this->order_lines = [];
        $this->order = $order;
        $this->currency = $this->dataHelper->getOrderCurrency($this->order);
        $this->process_items();
        $this->process_shipping();
        $this->process_fees();
        $this->process_gift_cards();
        $this->process_mismatch();
        return ['lines' => $this->get_order_lines()];
    }
    private function process_mismatch()
    {
        $orderTotal = (float) $this->order->get_total();
        $orderTotalRounded = round($orderTotal, 2);
        $linesTotal = array_sum(array_map(static function ($line) {
            return $line['totalAmount']['value'];
        }, $this->order_lines));
        $linesTotalRounded = round($linesTotal, 2);
        $orderTotalDiff = $orderTotalRounded - $linesTotalRounded;
        if (empty($orderTotalDiff)) {
            return;
        }
        $mismatch = ['type' => $orderTotalDiff > 0 ? 'surcharge' : 'discount', 'name' => __('Rounding difference', 'mollie-payments-for-woocommerce'), 'quantity' => 1, 'vatRate' => 0, 'unitPrice' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($orderTotalDiff, $this->currency)], 'totalAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($orderTotalDiff, $this->currency)], 'vatAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue(0, $this->currency)], 'metadata' => ['order_item_id' => 'rounding_diff']];
        $this->order_lines[] = $mismatch;
    }
    /**
     * Get order lines formatted for Mollie Orders API.
     *
     * @access private
     * @return mixed
     */
    private function get_order_lines()
    {
        return $this->order_lines;
    }
    /**
     * Process WooCommerce order items to Mollie Orders API - order lines.
     *
     * @access private
     */
    private function process_items()
    {
        foreach ($this->order->get_items() as $cart_item) {
            if ($cart_item['quantity']) {
                do_action($this->pluginId . '_orderlines_process_items_before_getting_product_id', $cart_item);
                if ($cart_item['variation_id']) {
                    $product = wc_get_product($cart_item['variation_id']);
                } else {
                    $product = wc_get_product($cart_item['product_id']);
                }
                $this->currency = $this->dataHelper->getOrderCurrency($this->order);
                $vatRate = round($this->get_item_vatRate($cart_item, $product), 2);
                $wcTotalValue = $this->get_item_total_amount($cart_item);
                $wcUnitPrice = $this->get_item_price($cart_item);
                // Calculate Mollie prices, they expect price including VAT
                $mollieTotal = $this->getMolliePrice($wcTotalValue, $vatRate);
                $mollieUnit = $this->getMolliePrice($wcUnitPrice, $vatRate);
                $mollie_order_item = ['sku' => $this->get_item_reference($product), 'type' => $product instanceof \WC_Product && $product->is_virtual() ? 'digital' : 'physical', 'name' => $this->get_item_name($cart_item), 'quantity' => $this->get_item_quantity($cart_item), 'vatRate' => $vatRate, 'unitPrice' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($mollieUnit['grossPrice'], $this->currency)], 'totalAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($mollieTotal['grossPrice'], $this->currency)], 'vatAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($mollieTotal['vatAmount'], $this->currency)], 'discountAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($this->get_item_discount_amount($cart_item), $this->currency)], 'metadata' => ['order_item_id' => $cart_item->get_id()], 'productUrl' => $product instanceof \WC_Product ? $product->get_permalink() : null];
                if ($this->get_item_total_amount($cart_item) < 0) {
                    $mollie_order_item['type'] = 'discount';
                    unset($mollie_order_item['discountAmount']);
                    $mollie_order_item['vatAmount']['value'] = $this->dataHelper->formatCurrencyValue(0, $this->currency);
                }
                if ($product instanceof \WC_Product && $product->get_image_id()) {
                    $productImage = wp_get_attachment_image_src($product->get_image_id(), 'full');
                    if (isset($productImage[0]) && wc_is_valid_url($productImage[0])) {
                        $mollie_order_item['imageUrl'] = $productImage[0];
                    }
                }
                $paymentMethod = $this->order->get_payment_method();
                if ($paymentMethod === 'mollie_wc_gateway_' . Constants::VOUCHER && $product instanceof \WC_Product) {
                    $categories = Voucher::getCategoriesForProduct($product);
                    if ($categories) {
                        $mollie_order_item['category'] = array_shift($categories);
                    }
                }
                $this->order_lines[] = $mollie_order_item;
                do_action($this->pluginId . '_orderlines_process_items_after_processing_item', $cart_item);
            }
        }
    }
    /**
     * Process WooCommerce shipping to Mollie Orders API - order lines.
     *
     * @access private
     */
    private function process_shipping()
    {
        $shipping_methods = $this->order->get_shipping_methods();
        if ($shipping_methods) {
            foreach ($shipping_methods as $shipping_method) {
                $vatRate = 0;
                if ($shipping_method->get_total_tax() > 0 && $shipping_method->get_total() > 0) {
                    $vatRate = round($shipping_method->get_total_tax() / $shipping_method->get_total(), 4) * 100;
                }
                $shipping = ['type' => 'shipping_fee', 'name' => $shipping_method->get_name() ?: __('Shipping', 'mollie-payments-for-woocommerce'), 'quantity' => 1, 'vatRate' => $vatRate, 'unitPrice' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($shipping_method->get_total() + $shipping_method->get_total_tax(), $this->currency)], 'totalAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($shipping_method->get_total() + $shipping_method->get_total_tax(), $this->currency)], 'vatAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($shipping_method->get_total_tax(), $this->currency)], 'metadata' => ['order_item_id' => $shipping_method->get_id()]];
                $this->order_lines[] = $shipping;
            }
        }
    }
    /**
     * Process fees.
     *
     * @access private
     */
    private function process_fees()
    {
        if (!empty($this->order->get_items('fee'))) {
            foreach ($this->order->get_items('fee') as $cart_fee) {
                if ($cart_fee['tax_status'] === 'taxable') {
                    // Calculate tax rate.
                    $tmp_rates = WC_Tax::get_rates($cart_fee['tax_class']);
                    $vat = array_shift($tmp_rates);
                    $cart_fee_vat_rate = isset($vat['rate']) ? $vat['rate'] : 0;
                    $cart_fee_tax_amount = $cart_fee['total_tax'];
                    $cart_fee_total = $cart_fee['total'] + $cart_fee['total_tax'];
                    /*This is the equation Mollie uses to validate our input*/
                    $validTax = $cart_fee_total * ($cart_fee_vat_rate / (100 + $cart_fee_vat_rate)) === (float) $cart_fee_tax_amount || $cart_fee_total === 0;
                    if (!$validTax) {
                        /*inverse of the equation Mollie uses to validate our input,
                          so we don't fail when cart has mixed taxes*/
                        $cart_fee_vat_rate = $cart_fee_tax_amount * 100 / ($cart_fee_total - $cart_fee_tax_amount);
                    }
                } else {
                    $cart_fee_vat_rate = 0;
                    $cart_fee_tax_amount = 0;
                    $cart_fee_total = $cart_fee['total'];
                }
                if (empty(round(floatval($cart_fee_total), 2))) {
                    continue;
                }
                $fee = ['type' => $cart_fee_total > 0 ? 'surcharge' : 'discount', 'name' => $cart_fee['name'], 'quantity' => 1, 'vatRate' => $this->dataHelper->formatCurrencyValue($cart_fee_vat_rate, $this->currency), 'unitPrice' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($cart_fee_total, $this->currency)], 'totalAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($cart_fee_total, $this->currency)], 'vatAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue($cart_fee_tax_amount, $this->currency)], 'metadata' => ['order_item_id' => $cart_fee->get_id()]];
                $this->order_lines[] = $fee;
            }
            // End foreach().
        }
        // End if().
    }
    /**
     * Process Gift Cards
     *
     * @access private
     */
    private function process_gift_cards()
    {
        if (!empty($this->order->get_items('gift_card'))) {
            foreach ($this->order->get_items('gift_card') as $cart_gift_card) {
                $gift_card = ['type' => 'gift_card', 'name' => $cart_gift_card->get_name(), 'unitPrice' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue(-$cart_gift_card->get_amount(), $this->currency)], 'vatRate' => 0, 'quantity' => 1, 'totalAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue(-$cart_gift_card->get_amount(), $this->currency)], 'vatAmount' => ['currency' => $this->currency, 'value' => $this->dataHelper->formatCurrencyValue(0, $this->currency)]];
                $this->order_lines[] = $gift_card;
            }
        }
    }
    // Helpers.
    /**
     * Get cart item name.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item $cart_item Cart item.
     *
     * @return string $item_name Cart item name.
     */
    private function get_item_name($cart_item)
    {
        $item_name = $cart_item->get_name();
        return html_entity_decode(wp_strip_all_tags($item_name));
    }
    /**
     * Calculate item tax percentage.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item $cart_item Cart item.
     *
     * @return integer $item_tax_amount Item tax amount.
     */
    private function get_item_tax_amount($cart_item)
    {
        return $cart_item['line_tax'];
    }
    /**
     * Calculate item tax percentage.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item  $cart_item Cart item.
     * @param  null|false|\WC_Product $product   Product object.
     *
     * @return integer $item_vatRate Item tax percentage formatted for Mollie Orders API.
     */
    private function get_item_vatRate($cart_item, $product)
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
     * Get cart item price.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item $cart_item Cart item.
     *
     * @return integer $item_price Cart item price.
     */
    private function get_item_price($cart_item)
    {
        $item_subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
        return $item_subtotal / $cart_item['quantity'];
    }
    /**
     * Get cart item quantity.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item $cart_item Cart item.
     *
     * @return integer $item_quantity Cart item quantity.
     */
    private function get_item_quantity($cart_item)
    {
        return $cart_item['quantity'];
    }
    /**
     * Get cart item SKU.
     *
     * Returns SKU or product ID.
     *
     * @since 1.0
     *
     * @access private
     *
     * @param null|false|\WC_Product $product Product object.
     *
     * @return false|string $item_reference Cart item reference.
     */
    private function get_item_reference($product)
    {
        if ($product && $product->get_sku()) {
            $item_reference = $product->get_sku();
        } elseif ($product) {
            $item_reference = $product->get_id();
        } else {
            $item_reference = '';
        }
        return substr(strval($item_reference), 0, 64);
    }
    /**
     * Get cart item discount.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item $cart_item Cart item.
     *
     * @return integer $item_discount_amount Cart item discount.
     */
    private function get_item_discount_amount($cart_item)
    {
        if ($cart_item['line_subtotal'] > $cart_item['line_total']) {
            $item_discount_amount = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] - $cart_item['line_total'] - $cart_item['line_tax'];
        } else {
            $item_discount_amount = 0;
        }
        return $item_discount_amount;
    }
    /**
     * Get cart item total amount.
     *
     * @since  1.0
     * @access private
     *
     * @param  WC_Order_Item $cart_item Cart item.
     *
     * @return integer $item_total_amount Cart item total amount.
     */
    private function get_item_total_amount($cart_item)
    {
        return $cart_item['line_total'] + $cart_item['line_tax'];
    }
    /**
     * Build price data for Mollie API.
     *
     * @param float $wcPrice
     * @param float $vatRate
     * @return float[]
     */
    private function getMolliePrice(float $wcPrice, float $vatRate): array
    {
        $grossPrice = wc_prices_include_tax() ? $wcPrice : $wcPrice * (1 + $vatRate / 100);
        return ['grossPrice' => $grossPrice, 'vatAmount' => $grossPrice * ($vatRate / (100 + $vatRate))];
    }
}
