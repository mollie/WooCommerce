<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Traits;

use Mollie\WooCommerceTests\Integration\Common\Factories\ProductFactory;
use Mollie\WooCommerceTests\Integration\Common\Factories\CouponFactory;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\DiscountPresets;

trait CreateTestProducts
{
    private ProductFactory $product_factory;
    private CouponFactory $coupon_factory;

    /**
     * Initialize factories
     */
    protected function initializeFactories(): void
    {
        $this->product_factory = new ProductFactory();
        $this->coupon_factory = new CouponFactory();
    }

    /**
     * Create all test products from presets
     * @throws \WC_Data_Exception
     */
    protected function createTestProducts(): void
    {
        if (!isset($this->product_factory)) {
            $this->initializeFactories();
        }

        foreach (array_keys(ProductPresets::get()) as $preset_name) {
            $preset = ProductPresets::get()[$preset_name];
            // Only create if doesn't exist
            if (!$this->product_factory->exists($preset['sku'])) {
                $this->product_factory->createFromPreset($preset_name);
            }
        }
    }

    /**
     * Create all test coupons from presets
     */
    protected function createTestCoupons(): void
    {
        if (!isset($this->coupon_factory)) {
            $this->initializeFactories();
        }

        foreach (DiscountPresets::get() as $preset_name => $preset) {
            // Only create coupons (skip manual fees)
            if (isset($preset['coupon_code']) && !$this->coupon_factory->exists($preset['coupon_code'])) {
                $this->coupon_factory->createFromPreset($preset_name);
            }
        }
    }
}
