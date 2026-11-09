<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\MerchantCapture;

use WC_Order;
use Mollie\WooCommerce\MerchantCapture\UI\StatusRenderer;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;
class OrderListPaymentColumn
{
    /** @var ContainerInterface $container */
    private $container;
    public function __construct($container)
    {
        $this->container = $container;
        add_filter('manage_edit-shop_order_columns', [$this, 'renderColumn']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderColumnValue'], 10, 2);
        # HPOS hooks
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'renderColumn']);
        add_action('woocommerce_shop_order_list_table_custom_column', function (string $column, WC_Order $order) {
            $this->renderColumnValue($column, $order->get_id());
        }, 10, 2);
    }
    public function renderColumn(array $columns): array
    {
        if (!$this->container->get('merchant.manual_capture.enabled')) {
            return $columns;
        }
        $newColumns = [];
        $mollieColumnAdded = \false;
        foreach ($columns as $columnId => $column) {
            $newColumns[$columnId] = $column;
            if ($columnId === 'order_number') {
                $newColumns['mollie_capture_payment_status'] = __('Payment Status', 'mollie-payments-for-woocommerce');
                $mollieColumnAdded = \true;
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
        $molliePaymentStatus = $order->get_meta(\Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::ORDER_PAYMENT_STATUS_META_KEY);
        (new StatusRenderer())($molliePaymentStatus);
    }
}
