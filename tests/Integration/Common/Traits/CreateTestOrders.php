<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Traits;

use WC_Order;
use Mollie\WooCommerceTests\Integration\Common\Factories\OrderFactory;

trait CreateTestOrders
{
    use CreateTestProducts;

    private OrderFactory $order_factory;

    /**
     * Initialize order factory
     */
    protected function initializeOrderFactory(): void
    {
        if (!isset($this->product_factory)) {
            $this->initializeFactories();
        }

        $this->order_factory = new OrderFactory($this->product_factory, $this->coupon_factory);
    }

    /**
     * Create a configured order using presets
     */
    protected function getConfiguredOrder(
        int    $customer_id,
        string $payment_method,
        array  $product_presets,
        array  $discount_presets = [],
        bool   $set_paid = true
    ): WC_Order
    {
        if (!isset($this->order_factory)) {
            $this->initializeOrderFactory();
        }

        return $this->order_factory->create(
            $customer_id,
            $payment_method,
            $product_presets,
            $discount_presets,
            $set_paid
        );
    }
}
