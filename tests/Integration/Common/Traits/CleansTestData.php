<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Traits;

trait CleansTestData
{
    /**
     * Clean all test data created by factories
     */
    protected function cleanupTestData(): void
    {
        if (isset($this->order_factory)) {
            $this->order_factory->cleanup();
        }

        if (isset($this->product_factory)) {
            $this->product_factory->cleanup();
        }

        if (isset($this->coupon_factory)) {
            $this->coupon_factory->cleanup();
        }
    }
}
