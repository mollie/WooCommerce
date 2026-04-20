<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\Psr\Log\LogLevel;
use WC_Order;
use WC_Subscriptions_Manager;
use WP_Error;
class MolliePayment extends \Mollie\WooCommerce\Payment\MollieObject
{
    public const ACTION_AFTER_REFUND_PAYMENT_CREATED = 'mollie-payments-for-woocommerce' . '_refund_payment_created';
    protected $pluginId;
    public function __construct($data, string $pluginId, Api $apiHelper, Settings $settingsHelper, Data $dataHelper, Logger $logger, RequestFactory $requestFactory)
    {
        $this->data = $data;
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
            // Is test mode enabled?
            $settingsHelper = $this->settingsHelper;
            $testMode = $settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey();
            self::$payment = $this->apiHelper->getApiClient($apiKey)->payments->get($paymentId);
            return parent::getPaymentObject($paymentId, $testMode = \false, $useCache = \true);
        } catch (ApiException $e) {
            $this->logger->debug(__FUNCTION__ . ": Could not load payment {$paymentId} (" . ($testMode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
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
        return $this->requestFactory->createRequest('payment', $order, $customerId);
    }
    /**
     * @return void
     */
    public function setActiveMolliePayment($orderId)
    {
        self::$paymentId = $this->getMolliePaymentIdFromPaymentObject();
        self::$customerId = $this->getMollieCustomerIdFromPaymentObject();
        self::$order = wc_get_order($orderId);
        self::$order->set_transaction_id($this->data->id);
        self::$order->update_meta_data('_mollie_payment_id', $this->data->id);
        self::$order->save();
        parent::setActiveMolliePayment($orderId);
    }
    public function getMolliePaymentIdFromPaymentObject()
    {
        if (isset($this->data->id)) {
            return $this->data->id;
        }
        return null;
    }
    public function getMollieCustomerIdFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }
        $payment = $this->getPaymentObject($payment);
        if (isset($payment->customerId)) {
            return $payment->customerId;
        }
        return null;
    }
    public function getSequenceTypeFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }
        $payment = $this->getPaymentObject($payment);
        if (isset($payment->sequenceType)) {
            return $payment->sequenceType;
        }
        return null;
    }
    /**
     * @param Payment $payment
     *
     */
    public function getMollieCustomerIbanDetailsFromPaymentObject($payment = null)
    {
        if ($payment === null) {
            $payment = $this->data->id;
        }
        $payment = $this->getPaymentObject($payment);
        /**
         * @var Payment $payment
         */
        $ibanDetails['consumerName'] = $payment->details->consumerName;
        $ibanDetails['consumerAccount'] = $payment->details->consumerAccount;
        return $ibanDetails;
    }
    /**
     * Process a payment object refund
     *
     * @param WC_Order $order
     * @param int    $orderId
     * @param object $paymentObject
     * @param null   $amount
     * @param string $reason
     *
     * @return bool | WP_Error
     */
    public function refund(\WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '')
    {
        $this->logger->debug(__METHOD__ . ' - ' . $orderId . ' - Try to process refunds for individual order line(s).');
        try {
            $paymentObject = $this->getActiveMolliePayment($orderId);
            if (!$paymentObject) {
                $errorMessage = "Could not find active Mollie payment for WooCommerce order ' . {$orderId}";
                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);
                return new WP_Error('1', $errorMessage);
            }
            if ($paymentObject->isAuthorized()) {
                return \true;
            }
            if (!$paymentObject->isPaid()) {
                $errorMessage = "Can not refund payment {$paymentObject->id} for WooCommerce order {$orderId} as it is not paid.";
                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);
                return new WP_Error('1', $errorMessage);
            }
            $this->logger->debug(__METHOD__ . ' - Create refund - payment object: ' . $paymentObject->id . ', WooCommerce order: ' . $orderId . ', amount: ' . $this->dataHelper->getOrderCurrency($order) . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));
            do_action($this->pluginId . '_create_refund', $paymentObject, $order);
            $apiKey = $this->settingsHelper->getApiKey();
            // Send refund to Mollie
            $refund = $this->apiHelper->getApiClient($apiKey)->payments->refund($paymentObject, ['amount' => ['currency' => $this->dataHelper->getOrderCurrency($order), 'value' => $this->dataHelper->formatCurrencyValue($amount, $this->dataHelper->getOrderCurrency($order))], 'description' => $reason]);
            $this->logger->debug(__METHOD__ . ' - Refund created - refund: ' . $refund->id . ', payment: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . $this->dataHelper->getOrderCurrency($order) . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));
            /**
             * After Payment Refund has been created
             *
             * @param Refund $refund
             * @param WC_Order $order
             */
            do_action(self::ACTION_AFTER_REFUND_PAYMENT_CREATED, $refund, $order);
            do_action_deprecated($this->pluginId . '_refund_created', [$refund, $order], '5.3.1', self::ACTION_AFTER_REFUND_PAYMENT_CREATED);
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %1$s%2$s%3$s - Payment: %4$s, Refund: %5$s', 'mollie-payments-for-woocommerce'),
                $this->dataHelper->getOrderCurrency($order),
                $amount,
                !empty($reason) ? ' (reason: ' . $reason . ')' : '',
                $refund->paymentId,
                $refund->id
            ));
            return \true;
        } catch (ApiException $e) {
            return new WP_Error(1, $e->getMessage());
        }
    }
    public function setPayment($data)
    {
        $this->data = $data;
    }
}
