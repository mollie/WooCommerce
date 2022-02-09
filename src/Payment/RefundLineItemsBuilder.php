<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\Shared\Data;
use stdClass;
use UnexpectedValueException;
use WC_Order_Item;

/**
 * Create the line items list to refund according to Mollie rest api documentation
 *
 * @link https://docs.mollie.com/reference/v2/orders-api/create-order-refund
 */
class RefundLineItemsBuilder
{
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * RefundLineItemsBuilder constructor.
     */
    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param string $currency
     * @param string $refundReason
     *
     * @return array
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     */
    public function buildLineItems(
        array $toRefundRemoteItems,
        array $toRefundItems,
        $currency,
        $refundReason
    ) {

        $toCancel = [
            'lines' => [],
        ];
        $toRefund = [
            'description' => $refundReason,
            'lines' => [],
        ];

        foreach ($toRefundItems as $toRefundItemId => $toRefundItem) {
            $toRefundRemoteItem = isset($toRefundRemoteItems[$toRefundItemId])
                ? $toRefundRemoteItems[$toRefundItemId]
                : null;

            if ($toRefundRemoteItem === null) {
                throw new UnexpectedValueException(
                    sprintf('Cannot refund %s item because it was not found in Mollie order. Aborting refund process. Try to do a refund by amount.', $toRefundItemId)
                );
            }

            $lineItem = $this->buildLineItem(
                $toRefundItem,
                $toRefundRemoteItem,
                $currency
            );

            if ($lineItem === []) {
                continue;
            }

            if (in_array($toRefundRemoteItem->status, OrderLineStatus::CAN_BE_CANCELED, true)) {
                $toCancel['lines'][] = $lineItem;
            }

            if (in_array($toRefundRemoteItem->status, OrderLineStatus::CAN_BE_REFUNDED, true)) {
                $toRefund['lines'][] = $lineItem;
            }
        }

        return ['toCancel' => $toCancel, 'toRefund' => $toRefund];
    }

    /**
     * @param WC_Order_Item $toRefundItem
     * @param string $currency
     *
     * @return array
     * @throws PartialRefundException
     */
    private function buildLineItem(
        WC_Order_Item $toRefundItem,
        stdClass $toRefundRemoteItem,
        $currency
    ) {

        $toRefundItemQuantity = abs($toRefundItem->get_quantity());
        $toRefundItemAmount = number_format(
            abs($toRefundItem->get_total() + $toRefundItem->get_total_tax()),
            2
        );
        $toRefundRemoteItemPrice = property_exists($toRefundRemoteItem->unitPrice, 'value') && $toRefundRemoteItem->unitPrice->value !== null
            ? $toRefundRemoteItem->unitPrice->value
            : 0;

        //as in Woo if the quantity is 0 but there is an amount, then quantity is 1
        if ($toRefundItemQuantity < 1 && $toRefundItemAmount > 0) {
            $toRefundItemQuantity = 1;
        }

        if ($toRefundItemAmount <= 0 || $toRefundItemQuantity < 1 || $toRefundRemoteItemPrice <= 0) {
            return [];
        }

        $toRefundRemoteItemAmount = number_format(
            $toRefundItemQuantity * $toRefundRemoteItemPrice,
            2
        );

        if ($toRefundRemoteItemAmount !== $toRefundItemAmount) {
            throw new PartialRefundException(
                __(
                    "Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line. Trying to process this as an amount refund instead.",
                    'mollie-payments-for-woocommerce'
                )
            );
        }

        $remoteClientData = [
            'id' => $toRefundRemoteItem->id,
            'quantity' => $toRefundItemQuantity,
        ];

        if (!empty($toRefundRemoteItem->discountAmount)) {
            $remoteClientData['amount'] = [
                'value' => $this->dataHelper->formatCurrencyValue($toRefundItemAmount, $currency),
                'currency' => $currency,
            ];
        }

        return $remoteClientData;
    }
}
