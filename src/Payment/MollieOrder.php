<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Exception;
use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\Refund\OrderItemsRefunder;
use Mollie\WooCommerce\Gateway\Refund\PartialRefundException;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\Psr\Log\LogLevel;
use UnexpectedValueException;
use WC_Order;
use WP_Error;
class MollieOrder extends \Mollie\WooCommerce\Payment\MollieObject
{
    public const ACTION_AFTER_REFUND_AMOUNT_CREATED = 'mollie-payments-for-woocommerce' . '_refund_amount_created';
    public const ACTION_AFTER_REFUND_ORDER_CREATED = 'mollie-payments-for-woocommerce' . '_refund_order_created';
    protected static $paymentId;
    protected static $customerId;
    protected static $order;
    protected static $payment;
    protected static $shop_country;
    /**
     * @var OrderItemsRefunder
     */
    private $orderItemsRefunder;
    protected $pluginId;
    /**
     * MollieOrder constructor.
     * @param OrderItemsRefunder $orderItemsRefunder
     * @param $data
     */
    public function __construct(OrderItemsRefunder $orderItemsRefunder, $data, string $pluginId, Api $apiHelper, Settings $settingsHelper, Data $dataHelper, Logger $logger, RequestFactory $requestFactory)
    {
        $this->data = $data;
        $this->orderItemsRefunder = $orderItemsRefunder;
        $this->pluginId = $pluginId;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->logger = $logger;
        $this->requestFactory = $requestFactory;
        $this->dataHelper = $dataHelper;
    }
    public function getPaymentObject($paymentId, $testMode = \false, $useCache = \true)
    {
        try {
            $testMode = $this->settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey();
            self::$payment = $this->apiHelper->getApiClient($apiKey)->orders->get($paymentId, ["embed" => "payments,refunds"]);
            return parent::getPaymentObject($paymentId, $testMode = \false, $useCache = \true);
        } catch (ApiException $e) {
            $this->logger->debug(__CLASS__ . __FUNCTION__ . ": Could not load payment {$paymentId} (" . ($testMode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }
        return null;
    }
    /**
     * @param $order
     * @param $customerId
     *
     * @return array
     */
    public function getPaymentRequestData($order, $customerId)
    {
        return $this->requestFactory->createRequest('order', $order, $customerId);
    }
    public function setActiveMolliePayment($orderId)
    {
        self::$paymentId = $this->getMolliePaymentIdFromPaymentObject();
        self::$customerId = $this->getMollieCustomerIdFromPaymentObject();
        self::$order = wc_get_order($orderId);
        self::$order->set_transaction_id($this->data->id);
        self::$order->update_meta_data('_mollie_order_id', $this->data->id);
        self::$order->save();
        return parent::setActiveMolliePayment($orderId);
    }
    public function getMolliePaymentIdFromPaymentObject()
    {
        $payment = $this->getPaymentObject($this->data->id);
        if (isset($payment->_embedded->payments[0]->id)) {
            return $payment->_embedded->payments[0]->id;
        }
    }
    public function getMollieCustomerIdFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }
        $payment = $this->getPaymentObject($payment);
        if (isset($payment->_embedded->payments[0]->customerId)) {
            return $payment->_embedded->payments[0]->customerId;
        }
    }
    public function getSequenceTypeFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }
        $payment = $this->getPaymentObject($payment);
        if (isset($payment->_embedded->payments[0]->sequenceType)) {
            return $payment->_embedded->payments[0]->sequenceType;
        }
    }
    public function getMollieCustomerIbanDetailsFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }
        $payment = $this->getPaymentObject($payment);
        $ibanDetails = [];
        if (isset($payment->_embedded->payments[0]->id)) {
            $actualPayment = new \Mollie\WooCommerce\Payment\MolliePayment($payment->_embedded->payments[0]->id, $this->pluginId, $this->apiHelper, $this->settingsHelper, $this->dataHelper, $this->logger, $this->requestFactory);
            $actualPayment = $actualPayment->getPaymentObject($actualPayment->data);
            /**
             * @var Payment $actualPayment
             */
            $ibanDetails['consumerName'] = $actualPayment->details->consumerName;
            $ibanDetails['consumerAccount'] = $actualPayment->details->consumerAccount;
        }
        return $ibanDetails;
    }
    /**
     * Process a payment object refund
     *
     * @param WC_Order $order
     * @param int      $orderId
     * @param object   $paymentObject
     * @param null     $amount
     * @param string   $reason
     *
     * @return bool|WP_Error
     */
    public function refund(WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '')
    {
        $this->logger->debug(__METHOD__ . ' - ' . $orderId . ' - Try to process refunds or cancels.');
        try {
            $paymentObject = $this->getPaymentObject($paymentObject->data);
            if (!$paymentObject) {
                $errorMessage = "Could not find active Mollie order for WooCommerce order ' . {$orderId}";
                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);
                throw new Exception($errorMessage);
            }
            if (!($paymentObject->isPaid() || $paymentObject->isAuthorized() || $paymentObject->isCompleted())) {
                $errorMessage = "Can not cancel or refund {$paymentObject->id} as order {$orderId} has status " . ucfirst($paymentObject->status) . ", it should be at least Paid, Authorized or Completed.";
                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);
                throw new Exception($errorMessage);
            }
            // Get all existing refunds
            $refunds = $order->get_refunds();
            // Get latest refund
            $woocommerceRefund = wc_get_order($refunds[0]);
            // Get order items from refund
            $items = $woocommerceRefund->get_items(['line_item', 'fee', 'shipping']);
            if (empty($items)) {
                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }
            // Compare total amount of the refund to the combined totals of all refunded items,
            // if the refund total is greater than sum of refund items, merchant is also doing a
            // 'Refund amount', which the Mollie API does not support. In that case, stop entire
            // process and warn the merchant.
            $totals = 0;
            foreach ($items as $itemId => $itemData) {
                $totals += $itemData->get_total() + $itemData->get_total_tax();
            }
            $totals = number_format(abs($totals), 2);
            // WooCommerce - sum of all refund items
            $checkAmount = $amount ? number_format((float) $amount, 2) : 0;
            // WooCommerce - refund amount
            if ($checkAmount !== $totals) {
                $errorMessage = _x('The sum of refunds for all order lines is not identical to the refund amount, so this refund will be processed as a payment amount refund, not an order line refund.', 'Order note error', 'mollie-payments-for-woocommerce');
                $order->add_order_note($errorMessage);
                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);
                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }
            $this->logger->debug('Try to process individual order item refunds or cancels.');
            try {
                return $this->orderItemsRefunder->refund($order, $items, $paymentObject, $reason);
            } catch (PartialRefundException $exception) {
                $this->logger->debug(__METHOD__ . ' - ' . $exception->getMessage());
                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            } catch (UnexpectedValueException $exception) {
                $order->add_order_note($exception->getMessage());
                $this->logger->debug(__METHOD__ . ' - ' . $exception->getMessage());
                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }
        } catch (Exception $exception) {
            $exceptionMessage = $exception->getMessage();
            $this->logger->debug(__METHOD__ . ' - ' . $exceptionMessage);
            return new WP_Error(1, $exceptionMessage);
        }
        return \false;
    }
    /**
     * @param $order
     * @param $orderId
     * @param $amount
     * @param $items
     * @param $paymentObject
     * @param $reason
     *
     * @return bool
     * @throws ApiException
     * @deprecated Not recommended because merchant will be charged for every refunded item, use OrderItemsRefunder instead.
     */
    public function refund_order_items($order, $orderId, $amount, $items, $paymentObject, $reason)
    {
        $this->logger->debug('Try to process individual order item refunds or cancels.');
        // Try to do the actual refunds or cancellations
        // Loop through items in the WooCommerce refund
        foreach ($items as $key => $item) {
            // Some merchants update orders with an order line with value 0, in that case skip processing that order line.
            $itemRefundAmountPrecheck = abs($item->get_total() + $item->get_total_tax());
            if ($itemRefundAmountPrecheck === 0) {
                continue;
            }
            // Loop through items in the Mollie payment object (Order)
            foreach ($paymentObject->lines as $line) {
                // If there is no metadata with the order item ID, this order can't process individual order lines
                if (empty($line->metadata->order_item_id)) {
                    $noteMessage = 'Refunds for this specific order can not be processed per order line. Trying to process this as an amount refund instead.';
                    $this->logger->debug(__METHOD__ . " - " . $noteMessage);
                    return $this->refund_amount($order, $amount, $paymentObject, $reason);
                }
                // Get the Mollie order line information that we need later
                $originalOrderItemId = $item->get_meta('_refunded_item_id', \true);
                $itemRefundAmount = abs($item->get_total() + $item->get_total_tax());
                if ($originalOrderItemId === $line->metadata->order_item_id) {
                    // Calculate the total refund amount for one order line
                    $lineTotalRefundAmount = abs($item->get_quantity()) * $line->unitPrice->value;
                    // Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line, so when merchants try that, warn them and block the process
                    if (number_format($lineTotalRefundAmount, 2) != number_format($itemRefundAmount, 2) || abs($item->get_quantity()) < 1) {
                        $noteMessage = sprintf("Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line. Use 'Refund amount' instead. The WooCommerce order item ID is %s, Mollie order line ID is %s.", $originalOrderItemId, $line->id);
                        $this->logger->debug(__METHOD__ . " - Order {$orderId}: " . $noteMessage);
                        throw new Exception(esc_html(sprintf("%s", $noteMessage)));
                    }
                    $this->processOrderItemsRefund($paymentObject, $item, $line, $order, $orderId, $itemRefundAmount, $reason, $items);
                }
            }
        }
        return \true;
    }
    /**
     * @param $order
     * @param $order_id
     * @param $amount
     * @param $paymentObject
     * @param $reason
     *
     * @return bool
     * @throws ApiException|Exception
     */
    public function refund_amount($order, $amount, $paymentObject, $reason)
    {
        $orderId = $order->get_id();
        $this->logger->debug('Try to process an amount refund (not individual order line)');
        $paymentObjectPayment = $this->getActiveMolliePayment($orderId);
        $apiKey = $this->settingsHelper->getApiKey();
        if ($paymentObject->isCreated() || $paymentObject->isAuthorized() || $paymentObject->isShipping()) {
            $noteMessage = sprintf(
                /* translators: Placeholder 1: payment status.*/
                _x('Can not refund order amount that has status %1$s at Mollie.', 'Order note error', 'mollie-payments-for-woocommerce'),
                ucfirst($paymentObject->status)
            );
            $order->add_order_note($noteMessage);
            $this->logger->debug(__METHOD__ . ' - ' . $noteMessage);
            throw new Exception(esc_html(sprintf("%s", $noteMessage)));
        }
        if ($paymentObject->isPaid() || $paymentObject->isShipping() || $paymentObject->isCompleted()) {
            $refund = $this->apiHelper->getApiClient($apiKey)->payments->refund($paymentObjectPayment, ['amount' => ['currency' => $this->dataHelper->getOrderCurrency($order), 'value' => $this->dataHelper->formatCurrencyValue($amount, $this->dataHelper->getOrderCurrency($order))], 'description' => $reason]);
            $noteMessage = sprintf(
                /* translators: Placeholder 1: Currency. Placeholder 2: Refund amount. Placeholder 3: Reason. Placeholder 4: Refund id.*/
                __('Amount refund of %1$s%2$s refunded in WooCommerce and at Mollie.%3$s Refund ID: %4$s.', 'mollie-payments-for-woocommerce'),
                $this->dataHelper->getOrderCurrency($order),
                $amount,
                !empty($reason) ? ' Reason: ' . $reason . '.' : '',
                $refund->id
            );
            $order->add_order_note($noteMessage);
            $this->logger->debug($noteMessage);
            /**
             * After Refund Amount Created
             *
             * @param Refund $refund
             * @param WC_Order $order
             * @param string $amount
             */
            do_action(self::ACTION_AFTER_REFUND_AMOUNT_CREATED, $refund, $order, $amount);
            do_action_deprecated($this->pluginId . '_refund_created', [$refund, $order], '5.3.1', self::ACTION_AFTER_REFUND_AMOUNT_CREATED);
            return \true;
        }
        return \false;
    }
    /**
     * @param Order $order
     * @param int                     $orderId
     */
    public function updatePaymentDataWithOrderData($order, $orderId)
    {
        $paymentCollection = $order->payments();
        foreach ($paymentCollection as $payment) {
            $payment->webhookUrl = $order->webhookUrl;
            $payment->metadata = ['order_id' => $orderId];
            $payment->update();
        }
    }
    /**
     * @param $paymentObject
     * @param $item
     * @param $line
     * @param $order
     * @param $orderId
     * @param $itemRefundAmount
     * @param $reason
     * @param $items
     *
     * @throws ApiException
     */
    protected function processOrderItemsRefund($paymentObject, $item, $line, $order, $orderId, $itemRefundAmount, $reason, $items): void
    {
        $apiKey = $this->settingsHelper->getApiKey();
        // Get the Mollie order
        $mollieOrder = $this->apiHelper->getApiClient($apiKey)->orders->get($paymentObject->id);
        $itemTotalAmount = abs(number_format($item->get_total() + $item->get_total_tax(), 2));
        // Prepare the order line to update
        if (!empty($line->discountAmount)) {
            $lines = ['lines' => [['id' => $line->id, 'quantity' => abs($item->get_quantity()), 'amount' => ['value' => $this->dataHelper->formatCurrencyValue($itemTotalAmount, $this->dataHelper->getOrderCurrency($order)), 'currency' => $this->dataHelper->getOrderCurrency($order)]]]];
        } else {
            $lines = ['lines' => [['id' => $line->id, 'quantity' => abs($item->get_quantity())]]];
        }
        if ($line->status === 'created' || $line->status === 'authorized') {
            // Returns null if successful.
            $refund = $mollieOrder->cancelLines($lines);
            $this->logger->debug(__METHOD__ . ' - Cancelled order line: ' . abs($item->get_quantity()) . 'x ' . $item->get_name() . '. Mollie order line: ' . $line->id . ', payment object: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . $this->data->getOrderCurrency($order) . wc_format_decimal($itemRefundAmount) . (!empty($reason) ? ', reason: ' . $reason : ''));
            if ($refund === null) {
                $noteMessage = sprintf(
                    /* translators: Placeholder 1: Number of items. Placeholder 2: Name of item. Placeholder 3: Currency. Placeholder 4: Amount.*/
                    __('%1$sx %2$s cancelled for %3$s%4$s in WooCommerce and at Mollie.', 'mollie-payments-for-woocommerce'),
                    abs($item->get_quantity()),
                    $item->get_name(),
                    $this->dataHelper->getOrderCurrency($order),
                    $itemRefundAmount
                );
            }
        }
        if ($line->status === 'paid' || $line->status === 'shipping' || $line->status === 'completed') {
            $lines['description'] = $reason;
            $refund = $mollieOrder->refund($lines);
            $this->logger->debug(__METHOD__ . ' - Refunded order line: ' . abs($item->get_quantity()) . 'x ' . $item->get_name() . '. Mollie order line: ' . $line->id . ', payment object: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . $this->data->getOrderCurrency($order) . wc_format_decimal($itemRefundAmount) . (!empty($reason) ? ', reason: ' . $reason : ''));
            $noteMessage = sprintf(
                /* translators: Placeholder 1: Number of items. Placeholder 2: Name of item. Placeholder 3: Currency. Placeholder 4: Amount. Placeholder 5: Reason. Placeholder 6: Refund Id. */
                __('%1$sx %2$s refunded for %3$s%4$s in WooCommerce and at Mollie.%5$s Refund ID: %6$s.', 'mollie-payments-for-woocommerce'),
                abs($item->get_quantity()),
                $item->get_name(),
                $this->dataHelper->getOrderCurrency($order),
                $itemRefundAmount,
                !empty($reason) ? ' Reason: ' . $reason . '.' : '',
                $refund->id
            );
        }
        do_action(self::ACTION_AFTER_REFUND_ORDER_CREATED, $refund, $order);
        do_action_deprecated($this->pluginId . '_refund_created', [$refund, $order], '5.3.1', self::ACTION_AFTER_REFUND_PAYMENT_CREATED);
        $order->add_order_note($noteMessage);
        $this->logger->debug($noteMessage);
        // drop item from array
        unset($items[$item->get_id()]);
    }
    public function setOrder($data)
    {
        $this->data = $data;
    }
}
