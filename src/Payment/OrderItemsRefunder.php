<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Shared\Data;
use UnexpectedValueException;
use WC_Order;
use WC_Order_Item;

/**
 * Refund a WooCommerce order by line items
 */
class OrderItemsRefunder
{
    /**
     * @var string
     */
    const ACTION_AFTER_REFUND_ORDER_ITEMS = 'mollie-payments-for-woocommerce_refund_items_created';
    /**
     * @var string
     */
    const ACTION_AFTER_CANCELED_ORDER_ITEMS = 'mollie-payments-for-woocommerce_line_items_cancelled';

    /**
     * @var RefundLineItemsBuilder
     */
    private $refundLineItemsBuilder;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var OrderEndpoint
     */
    private $ordersApiClient;

    /**
     * OrderItemsRefunder constructor.
     */
    public function __construct(
        RefundLineItemsBuilder $refundLineItemsBuilder,
        Data $dataHelper,
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
     * @throws PartialRefundException
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
        foreach ($items as $item) {
            $toRefundItemId = $item->get_meta('_refunded_item_id', true);

            if (!$toRefundItemId) {
                throw new UnexpectedValueException(
                    esc_html__(
                        'One of the WooCommerce order items does not have the refund item ID meta value associated to Mollie Order item.',
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
     * @return array
     * @throws UnexpectedValueException
     */
    private function normalizedRemoteItems(array $remoteItems)
    {
        $relatedRemoteItems = [];

        foreach ($remoteItems as $remoteItem) {
            $orderItemId = property_exists($remoteItem->metadata, 'order_item_id') && $remoteItem->metadata->order_item_id !== null
                ? $remoteItem->metadata->order_item_id
                : 0;

            if (!$orderItemId) {
                throw new UnexpectedValueException(
                    sprintf(
                        /* translators: Placeholder 1: item id. */
                        esc_html__(
                            'Impossible to retrieve the order item ID related to the remote item: %1$s. Try to do a refund by amount.',
                            'mollie-payments-for-woocommerce'
                        ),
                        esc_html($remoteItem->id)
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
     * @throws UnexpectedValueException
     */
    private function bailIfNoItemsToRefund(array $items, array $remoteItems)
    {
        if (empty($items) || empty($remoteItems)) {
            throw new UnexpectedValueException(
                esc_html__(
                    'Empty WooCommerce order items or mollie order lines.',
                    'mollie-payments-for-woocommerce'
                )
            );
        }
    }

    /**
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
