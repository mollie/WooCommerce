<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture;

class MollieCaptureSettings
{
    public function settings(array $advancedSettings, string $pluginName): array
    {
        $mollieCaptureSettings = [
            [
                'id' => $pluginName . '_place_payment_onhold',
                'title' => __('Placing payments on Hold', 'mollie-payments-for-woocommerce'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => [
                    'immediate_capture' => __('Capture payments immediately', 'mollie-payments-for-woocommerce'),
                    'later_capture' => __('Authorize payments for a later capture', 'mollie-payments-for-woocommerce'),
                ],
                'default' => 'immediate_capture',
                'desc' => sprintf(
                    __(
                        'Authorized payment can be captured or voided by changing the order status instead of doing it manually.',
                        'mollie-payments-for-woocommerce'
                    )
                ),
            ],
            [
                'id' => $pluginName . '_capture_or_void',
                'title' => __(
                    'Capture or void on status change',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'default' => 'no',
                'desc' => __(
                    'Capture authorized payments automatically when setting the order status to Processing or Completed. Void the payment by setting the order status Canceled.',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $pluginName . '_sectionend',
                'type' => 'sectionend',
            ],
        ];

        return array_merge($advancedSettings, $mollieCaptureSettings);
    }
}
