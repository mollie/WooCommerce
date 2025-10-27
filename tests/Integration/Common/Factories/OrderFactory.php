<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Factories;

use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\DiscountPresets;

class OrderFactory
{
    private array $created_order_ids = [];
    private ProductFactory $product_factory;
    private CouponFactory $coupon_factory;

    public function __construct(
        ProductFactory $product_factory = null,
        CouponFactory  $coupon_factory = null
    )
    {
        $this->product_factory = $product_factory ?? new ProductFactory();
        $this->coupon_factory = $coupon_factory ?? new CouponFactory();
    }

    /**
     * @param int $customer_id
     * @param string $payment_method
     * @param array $product_presets
     * @param array $discount_presets
     * @param bool $set_paid
     * @param string $transaction_id
     * @return WC_Order
     * @throws \WC_Data_Exception
     */
    public function create(
        int    $customer_id,
        string $payment_method,
        array  $product_presets,
        array  $discount_presets = [],
        bool   $set_paid = true,
        string $transaction_id = ''
    ): WC_Order
    {
        $products = $this->resolveProductPresets($product_presets);
        $discounts = $this->resolveDiscountPresets($discount_presets);

        $order = wc_create_order([
            'customer_id' => $customer_id,
            'set_paid' => $set_paid,
        ]);

        if (is_wp_error($order)) {
            throw new \WC_Data_Exception('order_creation_failed', 'Failed to create order');
        }

        $this->setBillingAddress($order);
        $this->addProductsToOrder($order, $products);
        $this->applyDiscountsToOrder($order, $discounts);

        $order->set_payment_method($payment_method);
        if (!$transaction_id) {
            $order->set_transaction_id(uniqid('tr_'));
        } else {
            $order->set_transaction_id($transaction_id);
        }
        $order->calculate_totals();
        $order->save();

        return $order;
    }

    /**
     * @param WC_Order $order
     */
    private function setBillingAddress(WC_Order $order): void
    {
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->set_billing_address_1('969 Market');
        $order->set_billing_city('San Francisco');
        $order->set_billing_state('CA');
        $order->set_billing_postcode('94103');
        $order->set_billing_country('US');
        $order->set_billing_email('john.doe@example.com');
        $order->set_billing_phone('(555) 555-5555');
    }

    /**
     * @param WC_Order $order
     * @param array $products
     * @throws \WC_Data_Exception
     */
    private function addProductsToOrder(WC_Order $order, array $products): void
    {
        foreach ($products as $product_data) {
            $product_id = null;

            if (!empty($product_data['sku'])) {
                $product_sku = $product_data['sku'];
                $product_id = wc_get_product_id_by_sku($product_sku);
            }

            if (!$product_id && isset($product_data['id'])) {
                $product_id = $product_data['id'];
            }

            if (!$product_id) {
                throw new \WC_Data_Exception('invalid_product', "Product not found - no valid SKU or ID provided");
            }

            $variation_id = $product_data['variation_id'] ?? 0;
            $product_type = $product_data['type'] ?? 'simple';

            $product = wc_get_product($variation_id ?: $product_id);

            if (!$product) {
                throw new \WC_Data_Exception('invalid_product', "Product {$product_id} not found");
            }

            // Use appropriate item class based on product type
            $item = $this->createOrderItem($product_type, $product_data, $product);

            if ($variation_id && $product->is_type('variation')) {
                $item->set_variation_data($product->get_variation_attributes());
            }

            $order->add_item($item);
        }
    }

    /**
     * @param WC_Order $order
     * @param array $discounts
     */
    private function applyDiscountsToOrder(WC_Order $order, array $discounts): void
    {
        foreach ($discounts as $discount) {
            if (isset($discount['coupon_code'])) {
                $order->apply_coupon($discount['coupon_code']);
            }

            if (isset($discount['fee'])) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_props([
                    'name' => $discount['fee']['name'],
                    'amount' => -abs($discount['fee']['amount']),
                    'total' => -abs($discount['fee']['amount']),
                ]);
                $order->add_item($fee);
            }
        }
    }

    /**
     * @param array $product_presets
     * @return array
     * @throws \WC_Data_Exception
     */
    private function resolveProductPresets(array $product_presets): array
    {
        $available_presets = ProductPresets::get();
        $products = [];

        foreach ($product_presets as $preset) {
            if (is_string($preset)) {
                if (!isset($available_presets[$preset])) {
                    throw new \WC_Data_Exception('invalid_preset', "Product preset '{$preset}' not found");
                }
                $products[] = $available_presets[$preset];
            } elseif (is_array($preset)) {
                $preset_name = $preset['preset'];
                $quantity = $preset['quantity'] ?? 1;

                if (!isset($available_presets[$preset_name])) {
                    throw new \WC_Data_Exception('invalid_preset', "Product preset '{$preset_name}' not found");
                }

                $product_data = $available_presets[$preset_name];
                $product_data['quantity'] = $quantity;
                $products[] = $product_data;
            }
        }

        return $products;
    }

    /**
     * @param array $discount_presets
     * @return array
     * @throws \WC_Data_Exception
     */
    private function resolveDiscountPresets(array $discount_presets): array
    {
        $available_presets = DiscountPresets::get();
        $discounts = [];

        foreach ($discount_presets as $preset) {
            if (!isset($available_presets[$preset])) {
                throw new \WC_Data_Exception('invalid_preset', "Discount preset '{$preset}' not found");
            }
            $discounts[] = $available_presets[$preset];
        }

        return $discounts;
    }

    /**
     * Delete all created orders
     */
    public function cleanup(): void
    {
        foreach ($this->created_order_ids as $order_id) {
            wp_delete_post($order_id, true);
        }

        $this->created_order_ids = [];
    }

    /**
     * @return array
     */
    public function getCreatedIds(): array
    {
        return $this->created_order_ids;
    }

    /**
     * @param string $product_type
     * @param array $product_data
     * @param \WC_Product $product
     * @return \WC_Order_Item_Product
     */
    private function createOrderItem(string $product_type, array $product_data, \WC_Product $product): \WC_Order_Item_Product
    {
        $item = new \WC_Order_Item_Product();

        $item->set_props([
            'product_id' => $product->get_id(),
            'variation_id' => $product_data['variation_id'] ?? 0,
            'quantity' => $product_data['quantity'],
            'subtotal' => $product->get_price() * $product_data['quantity'],
            'total' => $product->get_price() * $product_data['quantity'],
        ]);

        return $item;
    }
}
