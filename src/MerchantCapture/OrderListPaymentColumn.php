<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture;

use Mollie\WooCommerce\MerchantCapture\UI\StatusRenderer;

class OrderListPaymentColumn
{
    public function __construct()
    {
        add_filter('manage_edit-shop_order_columns', [$this, 'renderColumn']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderColumnValue'], 10, 2);
    }

    public function renderColumn(array $columns): array
    {
        $newColumns = [];
        $mollieColumnAdded = false;

        foreach ($columns as $columnId => $column) {
            $newColumns[$columnId] = $column;
            if ($columnId === 'order_number') {
                $newColumns['mollie_capture_payment_status'] = __(
                    'Payment Status',
                    'mollie-payments-for-woocommerce'
                );
                $mollieColumnAdded = true;
            }
        }

        if (!$mollieColumnAdded) {
            $newColumns['mollie_capture_payment_status'] = __('Payment Status', 'mollie-payments-for-woocommerce');
        }

        return $newColumns;
    }

    public function renderColumnValue(string $column, int $orderId)
    {
        if ($column !== 'mollie_capture_payment_status') {
            return;
        }
        /** @var \WC_Order $order */
        $order = wc_get_order($orderId);

        if (!is_a($order, \WC_Order::class)) {
            return;
        }

        $molliePaymentStatus = $order->get_meta(MerchantCaptureModule::ORDER_PAYMENT_STATUS_META_KEY);

        (new StatusRenderer())($molliePaymentStatus);
    }
}
