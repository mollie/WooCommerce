<?php

use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Refund;

/**
 * Refund a WooCommerce order by line items
 */
class Mollie_WC_Payment_OrderItemsRefunder
{
    const ACTION_AFTER_REFUND_ORDER_ITEMS = Mollie_WC_Plugin::PLUGIN_ID . '_refund_items_created';
    const ACTION_AFTER_CANCELED_ORDER_ITEMS = Mollie_WC_Plugin::PLUGIN_ID . '_line_items_cancelled';

    /**
     * @var Mollie_WC_Payment_RefundLineItemsBuilder
     */
    private $refundLineItemsBuilder;

    /**
     * @var Mollie_WC_Helper_Data
     */
    private $dataHelper;

    /**
     * @var OrderEndpoint
     */
    private $ordersApiClient;

    /**
     * OrderItemsRefunder constructor.
     * @param Mollie_WC_Payment_RefundLineItemsBuilder $refundLineItemsBuilder
     * @param Mollie_WC_Helper_Data $dataHelper
     * @param OrderEndpoint $ordersApiClient
     */
    public function __construct(
        Mollie_WC_Payment_RefundLineItemsBuilder $refundLineItemsBuilder,
        Mollie_WC_Helper_Data $dataHelper,
        OrderEndpoint $ordersApiClient
    ) {

        $this->refundLineItemsBuilder = $refundLineItemsBuilder;
        $this->dataHelper = $dataHelper;
        $this->ordersApiClient = $ordersApiClient;
    }

    /**
     * @param WC_Order $order WooCommerce Order
     * @param array $items WooCommerce Order Items
     * @param Order $remotePaymentObject Mollie Order service
     * @param string $refundReason The reason of refunding
     * @return bool
     * @throws ApiException When the API call fails for any reason
     * @throws UnexpectedValueException
     * @throws Mollie_WC_Payment_PartialRefundException
     */
    public function refund(
        WC_Order $order,
        array $items,
        Order $remotePaymentObject,
        $refundReason
    ) {

        $toRefundItems = $this->normalizedWooCommerceItemsList($items);
        $toRefundRemoteItems = $this->toRefundRemoteItems(
            $remotePaymentObject->lines,
            $toRefundItems
        );

        $this->bailIfNoItemsToRefund($toRefundItems, $toRefundRemoteItems);

        $lineItems = $this->refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $this->dataHelper->getOrderCurrency($order),
            $refundReason
        );

        $remoteOrder = $this->ordersApiClient->get($remotePaymentObject->id);

        if (!empty($lineItems['toCancel']['lines'])) {
            $this->cancelOrderLines($lineItems['toCancel'], $remoteOrder, $order);
        }

        if (!empty($lineItems['toRefund']['lines'])) {
            $this->refundOrderLines($lineItems['toRefund'], $remoteOrder, $order);
        }

        return true;
    }

    /**
     * Normalized version of WooCommerce order items where the key is the id of the item to refund
     *
     * @param array $items WooCommerce Order Items
     * @return array
     * @throws UnexpectedValueException
     */
    private function normalizedWooCommerceItemsList(array $items)
    {
        $toRefundItems = [];
        /** @var WC_Order_Item $item */
        foreach ($items as $key => $item) {
            $toRefundItemId = $item->get_meta('_refunded_item_id', true);

            if (!$toRefundItemId) {
                throw new UnexpectedValueException(
                    __(
                        'One of the WooCommerce order items does not have the refund item id meta value associated to Mollie Order item.',
                        'mollie-payments-for-woocommerce'
                    )
                );
            }

            $toRefundItems[$toRefundItemId] = $item;
        }

        return $toRefundItems;
    }

    /**
     * Given remote items of an order extract the ones for which the refund was requested
     *
     * @param array $remoteItems
     * @param array $toRefundItems
     * @return array
     * @throws UnexpectedValueException
     */
    private function toRefundRemoteItems(array $remoteItems, array $toRefundItems)
    {
        return array_intersect_key(
            $this->normalizedRemoteItems($remoteItems),
            $toRefundItems
        );
    }

    /**
     * Normalized version of remote items where the key is the id of the item to refund
     *
     * @param array $remoteItems
     * @return array
     * @throws UnexpectedValueException
     */
    private function normalizedRemoteItems(array $remoteItems)
    {
        $relatedRemoteItems = [];

        foreach ($remoteItems as $remoteItem) {
            $orderItemId = isset($remoteItem->metadata->order_item_id)
                ? $remoteItem->metadata->order_item_id
                : 0;

            if (!$orderItemId) {
                throw new UnexpectedValueException(
                    sprintf(
                        __(
                            'Impossible to retrieve the order item id related to the remote item: %1$s. Try to do a refund by amount.',
                            'mollie-payments-for-woocommerce'
                        ),
                        $remoteItem->id
                    )
                );
            }

            $relatedRemoteItems[$orderItemId] = $remoteItem;
        }

        return $relatedRemoteItems;
    }

    /**
     * Throw an exception if one of the given items list is empty
     *
     * @param array $items
     * @param array $remoteItems
     * @throws UnexpectedValueException
     */
    private function bailIfNoItemsToRefund(array $items, array $remoteItems)
    {
        if (empty($items) || empty($remoteItems)) {
            throw new UnexpectedValueException(
                __(
                    'Empty woocommerce order items or mollie order lines.',
                    'mollie-payments-for-woocommerce'
                )
            );
        }
    }

    /**
     * @param array $data
     * @param Order $remoteOrder
     * @param WC_Order $order
     * @throws ApiException
     */
    private function cancelOrderLines(array $data, Order $remoteOrder, WC_Order $order)
    {
        $remoteOrder->cancelLines($data);

        /**
         * Canceled Order Lines
         *
         * @param array $data Data sent to Mollie cancel endpoint
         * @param WC_Order $order
         */
        do_action(self::ACTION_AFTER_CANCELED_ORDER_ITEMS, $data, $order);
    }

    /**
     * @param array $data
     * @param Order $remoteOrder
     * @param WC_Order $order
     */
    private function refundOrderLines(array $data, Order $remoteOrder, WC_Order $order)
    {
        $refund = $remoteOrder->refund($data);

        /**
         * Refund Orders Lines
         *
         * @param Refund $refund Refund instance
         * @param WC_Order $order
         * @param array $data Data sent to Mollie refund endpoint
         */
        do_action(self::ACTION_AFTER_REFUND_ORDER_ITEMS, $refund, $order, $data);
    }
}
