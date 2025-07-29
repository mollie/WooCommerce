<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Fixtures;

class DiscountPresets
{
    public static function get(): array
    {
        return [
            'percentage_10' => [
                'coupon_code' => 'TEST10PERCENT',
                'type' => 'percent',
                'amount' => '10'
            ],
            'fixed_5' => [
                'coupon_code' => 'TEST5FIXED',
                'type' => 'fixed_cart',
                'amount' => '5.00'
            ],
            'manual_discount' => [
                'fee' => ['name' => 'Test Discount', 'amount' => 3.50]
            ],
        ];
    }
}
