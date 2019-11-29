<?php

/**
 * Create the line items list to refund according to Mollie rest api documentation
 *
 * @link https://docs.mollie.com/reference/v2/orders-api/create-order-refund
 */
class Mollie_WC_Payment_RefundLineItemsBuilder
{
    /**
     * @var Mollie_WC_Helper_Data
     */
    private $dataHelper;

    /**
     * RefundLineItemsBuilder constructor.
     * @param Mollie_WC_Helper_Data $dataHelper
     */
    public function __construct(Mollie_WC_Helper_Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param array $toRefundRemoteItems
     * @param array $toRefundItems
     * @param string $currency
     * @param string $refundReason
     * @return array
     * @throws Mollie_WC_Payment_PartialRefundException
     * @throws UnexpectedValueException
     */
    public function buildLineItems(
        array $toRefundRemoteItems,
        array $toRefundItems,
        $currency,
        $refundReason
    ) {

        $toCancel = [
            'description' => $refundReason,
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
                    "Cannot refund {$toRefundItemId} item because it was not found in Mollie order. Aborting refund process. Try to do a refund by amount."
                );
            }

            $lineItem = $this->buildLineItem(
                $toRefundItem,
                $toRefundRemoteItem,
                $currency
            );

            if (!$lineItem) {
                continue;
            }

            if (in_array($toRefundRemoteItem->status, Mollie_WC_OrderLineStatus::CAN_BE_CANCELED, true)) {
                $toCancel['lines'][] = $lineItem;
            }

            if (in_array($toRefundRemoteItem->status, Mollie_WC_OrderLineStatus::CAN_BE_REFUNDED, true)) {
                $toRefund['lines'][] = $lineItem;
            }
        }

        return compact('toCancel', 'toRefund');
    }

    /**
     * @param WC_Order_Item $toRefundItem
     * @param stdClass $toRefundRemoteItem
     * @param string $currency
     * @return array
     * @throws Mollie_WC_Payment_PartialRefundException
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
        $toRefundRemoteItemPrice = isset($toRefundRemoteItem->unitPrice->value)
            ? $toRefundRemoteItem->unitPrice->value
            : 0;

        if ($toRefundItemAmount <= 0 || $toRefundItemQuantity < 1 || $toRefundRemoteItemPrice <= 0) {
            return [];
        }

        $toRefundRemoteItemAmount = number_format(
            $toRefundItemQuantity * $toRefundRemoteItemPrice,
            2
        );

        if ($toRefundRemoteItemAmount !== $toRefundItemAmount) {
            throw new Mollie_WC_Payment_PartialRefundException(
                __(
                    'Mollie doesn\'t allow a partial refund of the full amount or quantity of at least one order line. Trying to process this as an amount refund instead.',
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
