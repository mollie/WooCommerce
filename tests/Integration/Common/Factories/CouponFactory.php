<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Factories;

use WC_Coupon;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\DiscountPresets;

class CouponFactory
{
    private array $created_coupon_ids = [];

    /**
     * @param string $preset_name
     * @return WC_Coupon
     * @throws \WC_Data_Exception
     */
    public function createFromPreset(string $preset_name): WC_Coupon
    {
        $presets = DiscountPresets::get();

        if (!isset($presets[$preset_name])) {
            throw new \WC_Data_Exception('invalid_preset', "Coupon preset '{$preset_name}' not found");
        }

        $preset = $presets[$preset_name];

        if (!isset($preset['coupon_code'])) {
            throw new \WC_Data_Exception('invalid_preset', "Preset '{$preset_name}' is not a coupon");
        }

        return $this->createCoupon($preset);
    }

    /**
     * @param array $preset
     * @return WC_Coupon
     */
    private function createCoupon(array $preset): WC_Coupon
    {
        $coupon = new WC_Coupon();
        $coupon->set_code($preset['coupon_code']);
        $coupon->set_discount_type($preset['type']);
        $coupon->set_amount($preset['amount']);
        $coupon->set_status('publish');
        $coupon->save();

        $this->created_coupon_ids[] = $coupon->get_id();

        return $coupon;
    }

    /**
     * @param string $coupon_code
     * @return bool
     */
    public function exists(string $coupon_code): bool
    {
        return (bool)wc_get_coupon_id_by_code($coupon_code);
    }

    /**
     * @param string $coupon_code
     * @return WC_Coupon|null
     */
    public function getByCode(string $coupon_code): ?WC_Coupon
    {
        $coupon_id = wc_get_coupon_id_by_code($coupon_code);

        return $coupon_id ? new WC_Coupon($coupon_id) : null;
    }

    /**
     * Delete all created coupons
     */
    public function cleanup(): void
    {
        foreach ($this->created_coupon_ids as $coupon_id) {
            wp_delete_post($coupon_id, true);
        }

        $this->created_coupon_ids = [];
    }

    /**
     * @return array
     */
    public function getCreatedIds(): array
    {
        return $this->created_coupon_ids;
    }
}
