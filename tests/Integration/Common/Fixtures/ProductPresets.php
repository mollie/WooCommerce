<?php
declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Fixtures;

class ProductPresets
{
    public static function get(): array
    {
        return [
            'simple' => [
                'sku' => 'DUMMY_SIMPLE_SKU_01',
                'name' => 'Test Simple Product',
                'price' => '10.00',
                'quantity' => 1,
                'type' => 'simple'
            ],
            'simple_expensive' => [
                'sku' => 'DUMMY_SIMPLE_SKU_02',
                'name' => 'Test Expensive Product',
                'price' => '199.99',
                'quantity' => 1,
                'type' => 'simple'
            ],
            /*'variable' => [
                'sku' => 'DUMMY_VARIABLE_SKU_01',
                'variation_id' => 20002,
                'name' => 'Test Variable Product',
                'price' => '25.00',
                'quantity' => 1,
                'type' => 'variable'
            ],*/
            'subscription' => [
                'name' => 'Dummy Subscription Product',
                'price' => '10.00',
                'quantity' => 1,
                'type' => 'subscription',
                'sku' => 'DUMMY SUB SKU',
                'subscription_period' => 'day',
                'subscription_period_interval' => 1,
                'subscription_length' => 0,
                'subscription_trial_period' => '',
                'subscription_trial_length' => 0,
                'subscription_price' => 10,
                'subscription_sign_up_fee' => 0,
            ]
        ];
    }
}
