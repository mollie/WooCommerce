<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayI;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use WC_Order;

class PaymentService
{
    public const PAYMENT_METHOD_TYPE_ORDER = 'order';
    public const PAYMENT_METHOD_TYPE_PAYMENT = 'payment';

    /**
     * @var MolliePaymentGatewayI
     */
    protected $gateway;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    /**
     * @var Data
     */
    protected $dataHelper;
    protected $apiHelper;
    protected $settingsHelper;
    protected $pluginId;
    /**
     * @var PaymentCheckoutRedirectService
     */
    protected $paymentCheckoutRedirectService;
    /**
     * @var string
     */
    protected $voucherDefaultCategory;

    /**
     * PaymentService constructor.
     */
    public function __construct(
        NoticeInterface $notice,
        Logger $logger,
        PaymentFactory $paymentFactory,
        Data $dataHelper,
        Api $apiHelper,
        Settings $settingsHelper,
        string $pluginId,
        PaymentCheckoutRedirectService $paymentCheckoutRedirectService,
        string $voucherDefaultCategory
    ) {

        $this->notice = $notice;
        $this->logger = $logger;
        $this->paymentFactory = $paymentFactory;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->pluginId = $pluginId;
        $this->paymentCheckoutRedirectService = $paymentCheckoutRedirectService;
        $this->voucherDefaultCategory = $voucherDefaultCategory;
    }

    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }

    public function processPayment($orderId, $order, $paymentMethod, $redirectUrl)
    {
        $this->logger->debug(
            "{$paymentMethod->getProperty('id')}: Start process_payment for order {$orderId}",
            [true]
        );
        $initialOrderStatus = $this->processInitialOrderStatus($paymentMethod);
        $customerId = $this->getUserMollieCustomerId($order);
        $apiKey = $this->settingsHelper->getApiKey();
        $hasBlocksEnabled = $this->dataHelper->isBlockPluginActive();
        $isClassicCheckout = isset($_REQUEST["wc-ajax"]) && $_REQUEST["wc-ajax"] === "checkout";
        if ($hasBlocksEnabled && !$isClassicCheckout) {
            $order = $this->correctSurchargeFee($order, $paymentMethod);
        }

        if ($this->needsSubscriptionSwitch($order, $orderId)) {
            return $this->processSubscriptionSwitch($order, $orderId, $customerId, $apiKey);
        }

        $molliePaymentType = $this->paymentTypeBasedOnGateway($paymentMethod);
        $molliePaymentType = $this->paymentTypeBasedOnProducts($order, $molliePaymentType);
        try {
            $paymentObject = $this->paymentFactory->getPaymentObject($molliePaymentType);
        } catch (ApiException $exception) {
            return $this->paymentObjectFailure($exception);
        }
        try {
            $paymentObject = $this->processPaymentForMollie(
                $molliePaymentType,
                $orderId,
                $paymentObject,
                $order,
                $customerId,
                $apiKey
            );

            $this->saveMollieInfo($order, $paymentObject);
            $this->saveSubscriptionMandateData($orderId, $apiKey, $customerId, $paymentObject, $order);
            do_action($this->pluginId . '_payment_created', $paymentObject, $order);
            $this->updatePaymentStatusForDelayedMethods($paymentObject, $order, $initialOrderStatus);
            $this->reportPaymentSuccess($paymentObject, $orderId, $order, $paymentMethod);
            return [
                'result' => 'success',
                'redirect' => $this->getProcessPaymentRedirect(
                    $paymentMethod,
                    $order,
                    $paymentObject,
                    $redirectUrl
                ),
            ];
        } catch (ApiException $error) {
            $paymentMethodId = $paymentMethod->getProperty('id');
            $this->reportPaymentCreationFailure($orderId, $error, $paymentMethodId);
        }
        return ['result' => 'failure'];
    }

    /**
     * @param WC_Order $order
     * @param PaymentMethodI $paymentMethod
     */
    protected function correctSurchargeFee($order, $paymentMethod)
    {
        $fees = $order->get_fees();
        $surcharge = $paymentMethod->surcharge();
        $gatewaySettings = $paymentMethod->getMergedProperties();
        $totalAmount = (float) $order->get_total();
        $aboveMaxLimit = $surcharge->aboveMaxLimit($totalAmount, $gatewaySettings);
        $amount = $aboveMaxLimit ? 0.0 : $surcharge->calculateFeeAmountOrder($order, $gatewaySettings);
        $gatewayHasSurcharge = $amount !== 0.0;
        $gatewayFeeLabel = get_option(
            'mollie-payments-for-woocommerce_gatewayFeeLabel',
            $surcharge->defaultFeeLabel()
        );

        $correctedFee = false;
        foreach ($fees as $fee) {
            $feeName = $fee->get_name();
            $feeId = $fee->get_id();
            $hasMollieFee = strpos($feeName, $gatewayFeeLabel) !== false;
            if ($hasMollieFee) {
                if ($amount == (float)$fee->get_amount('edit')) {
                    $correctedFee = true;
                    continue;
                }
                if (!$gatewayHasSurcharge) {
                    $this->removeOrderFee($order, $feeId);
                    $correctedFee = true;
                    continue;
                }
                $this->removeOrderFee($order, $feeId);
                $this->orderAddFee($order, $amount, $gatewayFeeLabel);
                $correctedFee = true;
            }
        }
        if (!$correctedFee) {
            if ($gatewayHasSurcharge) {
                $this->orderAddFee($order, $amount, $gatewayFeeLabel);
            }
        }
        return $order;
    }

    /**
     * @param WC_Order $order
     * @param int $feeId
     * @throws \Exception
     */
    protected function removeOrderFee(\WC_Order $order, int $feeId): \WC_Order
    {
        $order->remove_item($feeId);
        wc_delete_order_item($feeId);
        $order->calculate_totals();
        return $order;
    }

    protected function orderAddFee($order, $amount, $surchargeName)
    {
        $item_fee = new \WC_Order_Item_Fee();
        $item_fee->set_name($surchargeName);
        $item_fee->set_amount($amount);
        $item_fee->set_total($amount);
        $item_fee->set_tax_status('taxable');
        $order->add_item($item_fee);
        $order->calculate_totals();
    }

    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order $order
     * @param MollieOrder|MolliePayment $paymentObject
     *
     *
     */
    public function getProcessPaymentRedirect(
        PaymentMethodI $paymentMethod,
        $order,
        $paymentObject,
        string $redirectUrl
    ) {

        $this->paymentCheckoutRedirectService->setStrategy($paymentMethod);
        return $this->paymentCheckoutRedirectService->executeStrategy(
            $paymentMethod,
            $order,
            $paymentObject,
            $redirectUrl
        );
    }

    /**
     * @param $order
     * @param $test_mode
     * @return null|string
     */
    protected function getUserMollieCustomerId($order)
    {
        $order_customer_id = $order->get_customer_id();
        $apiKey = $this->settingsHelper->getApiKey();

        return $this->dataHelper->getUserMollieCustomerId($order_customer_id, $apiKey);
    }

    protected function paymentTypeBasedOnGateway($paymentMethod)
    {
        $optionName = $this->pluginId . '_' . 'api_switch';
        $apiSwitchOption = get_option($optionName);
        $paymentType = $apiSwitchOption ?: self::PAYMENT_METHOD_TYPE_ORDER;
        $isBankTransferGateway = $paymentMethod->getProperty('id') === Constants::BANKTRANSFER;
        if ($isBankTransferGateway && $paymentMethod->isExpiredDateSettingActivated()) {
            $paymentType = self::PAYMENT_METHOD_TYPE_PAYMENT;
        }

        return $paymentType;
    }

    /**
     * CHECK WOOCOMMERCE PRODUCTS
     * Make sure all cart items are real WooCommerce products,
     * not removed products or virtual ones (by WooCommerce Events Manager etc).
     * If products are virtual, use Payments API instead of Orders API
     *
     * @param \WC_Order $order
     *
     * @param string $molliePaymentType
     *
     * @return string
     */
    protected function paymentTypeBasedOnProducts($order, $molliePaymentType)
    {
        foreach ($order->get_items() as $cart_item) {
            if ($cart_item['quantity']) {
                do_action(
                    $this->pluginId
                    . '_orderlines_process_items_before_getting_product_id',
                    $cart_item
                );

                if ($cart_item['variation_id']) {
                    $product = wc_get_product($cart_item['variation_id']);
                } else {
                    $product = wc_get_product($cart_item['product_id']);
                }

                if ($product === false) {
                    $molliePaymentType = self::PAYMENT_METHOD_TYPE_PAYMENT;
                    do_action(
                        $this->pluginId
                        . '_orderlines_process_items_after_processing_item',
                        $cart_item
                    );
                    break;
                }
                do_action(
                    $this->pluginId
                    . '_orderlines_process_items_after_processing_item',
                    $cart_item
                );
            }
        }
        return $molliePaymentType;
    }

    /**
     * @param MollieOrder $paymentObject
     * @param \WC_Order $order
     * @param $customer_id
     * @param $test_mode
     *
     * @return array
     * @throws ApiException
     */
    protected function processAsMollieOrder(
        MollieOrder $paymentObject,
        $order,
        $customer_id,
        $apiKey
    ) {

        $molliePaymentType = self::PAYMENT_METHOD_TYPE_ORDER;
        $paymentRequestData = $paymentObject->getPaymentRequestData(
            $order,
            $customer_id,
            $this->voucherDefaultCategory
        );

        $data = array_filter($paymentRequestData);

        $data = apply_filters(
            'woocommerce_' . $this->gateway->id . '_args',
            $data,
            $order
        );

        do_action(
            $this->pluginId . '_create_payment',
            $data,
            $order
        );

        // Create Mollie payment with customer id.
        try {
            $this->logger->debug(
                'Creating payment object: type Order, first try creating a Mollie Order.'
            );

            // Only enable this for hardcore debugging!
            $apiCallLog = [
                'amount' => isset($data['amount']) ? $data['amount'] : '',
                'redirectUrl' => isset($data['redirectUrl'])
                    ? $data['redirectUrl'] : '',
                'webhookUrl' => isset($data['webhookUrl'])
                    ? $data['webhookUrl'] : '',
                'method' => isset($data['method']) ? $data['method'] : '',
                'payment' => isset($data['payment']) ? $data['payment']
                    : '',
                'locale' => isset($data['locale']) ? $data['locale'] : '',
                'metadata' => isset($data['metadata']) ? $data['metadata']
                    : '',
                'orderNumber' => isset($data['orderNumber'])
                    ? $data['orderNumber'] : '',
                'lines' => isset($data['lines']) ? $data['lines'] : '', ];

            $this->logger->debug(json_encode($apiCallLog));
            $paymentOrder = $paymentObject;
            $paymentObject = $this->apiHelper->getApiClient($apiKey)->orders->create($data);
            $this->logger->debug(json_encode($paymentObject));
            $settingsHelper = $this->settingsHelper;
            if ($settingsHelper->getOrderStatusCancelledPayments() === 'cancelled') {
                $orderId = $order->get_id();
                $orderWithPayments = $this->apiHelper->getApiClient($apiKey)->orders->get(
                    $paymentObject->id,
                    ["embed" => "payments"]
                );
                $paymentOrder->updatePaymentDataWithOrderData($orderWithPayments, $orderId);
            }
        } catch (ApiException $e) {
            $this->handleMollieOutage($e);
            //if exception is 422 do not try to create a payment
            $this->handleMollieFraudRejection($e);
            // Don't try to create a Mollie Payment for Klarna payment methods
            $order_payment_method = $order->get_payment_method();
            $orderMandatoryPaymentMethods = [
                'mollie_wc_gateway_klarnapaylater',
                'mollie_wc_gateway_klarnasliceit',
                'mollie_wc_gateway_klarnapaynow',
                'mollie_wc_gateway_klarna',
                'mollie_wc_gateway_billie',
                'mollie_wc_gateway_in3',
            ];

            if (in_array($order_payment_method, $orderMandatoryPaymentMethods, true)) {
                $this->logger->debug(
                    'Creating payment object: type Order failed, stopping process.'
                );
                throw $e;
            }

            $this->logger->debug(
                'Creating payment object: type Order, first try failed: '
                . $e->getMessage()
            );

            // Unset missing customer ID
            unset($data['payment']['customerId']);

            try {
                if ($e->getField() !== 'payment.customerId') {
                    $this->logger->debug(
                        'Creating payment object: type Order, did not fail because of incorrect customerId, so trying Payment now.'
                    );
                    throw $e;
                }

                // Retry without customer id.
                $this->logger->debug(
                    'Creating payment object: type Order, second try, creating a Mollie Order without a customerId.'
                );
                $paymentObject = $this->apiHelper->getApiClient(
                    $apiKey
                )->orders->create($data);
            } catch (ApiException $e) {
                // Set Mollie payment type to payment, when creating a Mollie Order has failed
                $molliePaymentType = self::PAYMENT_METHOD_TYPE_PAYMENT;
            }
        }
        return [$paymentObject, $molliePaymentType];
    }

    /**
     * @param \WC_Order $order
     * @param $customer_id
     * @param $test_mode
     *
     * @return Payment $paymentObject
     * @throws ApiException
     */
    protected function processAsMolliePayment(
        \WC_Order $order,
        $customer_id,
        $apiKey
    ) {

        $paymentObject = $this->paymentFactory->getPaymentObject(
            self::PAYMENT_METHOD_TYPE_PAYMENT
        );
        $paymentRequestData = $paymentObject->getPaymentRequestData(
            $order,
            $customer_id
        );

        $data = array_filter($paymentRequestData);

        $data = apply_filters(
            'woocommerce_' . $this->gateway->id . '_args',
            $data,
            $order
        );

        try {
            // Only enable this for hardcore debugging!
            $apiCallLog = [
                'amount' => isset($data['amount']) ? $data['amount'] : '',
                'description' => isset($data['description'])
                    ? $data['description'] : '',
                'redirectUrl' => isset($data['redirectUrl'])
                    ? $data['redirectUrl'] : '',
                'webhookUrl' => isset($data['webhookUrl'])
                    ? $data['webhookUrl'] : '',
                'method' => isset($data['method']) ? $data['method'] : '',
                'issuer' => isset($data['issuer']) ? $data['issuer'] : '',
                'locale' => isset($data['locale']) ? $data['locale'] : '',
                'dueDate' => isset($data['dueDate']) ? $data['dueDate'] : '',
                'metadata' => isset($data['metadata']) ? $data['metadata']
                    : '',
            ];

            $this->logger->debug($apiCallLog);

            // Try as simple payment
            $paymentObject = $this->apiHelper->getApiClient(
                $apiKey
            )->payments->create($data);
        } catch (ApiException $e) {
            $this->handleMollieOutage($e);
            $message = $e->getMessage();
            $this->logger->debug($message);
            throw $e;
        }
        return $paymentObject;
    }

    /**
     * @param $molliePaymentType
     * @param $orderId
     * @param MollieOrder|MolliePayment $paymentObject
     * @param \WC_Order $order
     * @param $customer_id
     * @param $test_mode
     *
     * @return mixed|Payment|MollieOrder
     * @throws ApiException
     */
    protected function processPaymentForMollie(
        $molliePaymentType,
        $orderId,
        $paymentObject,
        $order,
        $customer_id,
        $apiKey
    ) {
        //
        // PROCESS REGULAR PAYMENT AS MOLLIE ORDER
        //
        if ($molliePaymentType === self::PAYMENT_METHOD_TYPE_ORDER) {
            $this->logger->debug(
                "{$this->gateway->id}: Create Mollie payment object for order {$orderId}",
                [true]
            );

            list($paymentObject, $molliePaymentType) = $this->processAsMollieOrder(
                $paymentObject,
                $order,
                $customer_id,
                $apiKey
            );
        }

        //
        // PROCESS REGULAR PAYMENT AS MOLLIE PAYMENT
        //

        if ($molliePaymentType === self::PAYMENT_METHOD_TYPE_PAYMENT) {
            $this->logger->debug(
                'Creating payment object: type Payment, creating a Payment.'
            );

            $paymentObject = $this->processAsMolliePayment(
                $order,
                $customer_id,
                $apiKey
            );
        }

        return $paymentObject;
    }

    /**
     * @param $order
     * @param $payment
     */
    protected function saveMollieInfo($order, $payment)
    {
        // Get correct Mollie Payment Object
        $payment_object = $this->paymentFactory->getPaymentObject($payment);

        // Set active Mollie payment
        $payment_object->setActiveMolliePayment($order->get_id());

        // Get Mollie Customer ID
        $mollie_customer_id = $payment_object->getMollieCustomerIdFromPaymentObject($payment_object->data()->id);

        // Set Mollie customer
        $this->dataHelper->setUserMollieCustomerId($order->get_customer_id(), $mollie_customer_id);
    }

    /**
     * @param \WC_Order $order
     * @param string $new_status
     * @param string $note
     * @param bool $restore_stock
     */
    public function updateOrderStatus(\WC_Order $order, $new_status, $note = '', $restore_stock = true)
    {
        $order->update_status($new_status, $note);

        switch ($new_status) {
            case SharedDataDictionary::STATUS_ON_HOLD:
                if ($restore_stock === true) {
                    if (!$order->get_meta('_order_stock_reduced', true)) {
                        // Reduce order stock
                        wc_reduce_stock_levels($order->get_id());

                        $this->logger->debug(__METHOD__ . ":  Stock for order {$order->get_id()} reduced.");
                    }
                }

                break;

            case SharedDataDictionary::STATUS_PENDING:
            case SharedDataDictionary::STATUS_FAILED:
            case SharedDataDictionary::STATUS_CANCELLED:
                if ($order->get_meta('_order_stock_reduced', true)) {
                    // Restore order stock
                    $this->dataHelper->restoreOrderStock($order);

                    $this->logger->debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
                }

                break;
        }
    }

    /**
     * @param $orderId
     */
    protected function noValidMandateForSubsSwitchFailure($orderId): void
    {
        $this->logger->debug(
            $this->gateway->id . ': Subscription switch failed, no valid mandate for order #' . $orderId
        );
        $this->notice->addNotice(
            'error',
            __(
                'Subscription switch failed, no valid mandate found. Place a completely new order to change your subscription.',
                'mollie-payments-for-woocommerce'
            )
        );
        throw new ApiException(
            __('Failed switching subscriptions, no valid mandate.', 'mollie-payments-for-woocommerce')
        );
    }

    protected function subsSwitchCompleted($order): array
    {
        $order->payment_complete();

        $order->add_order_note(
            sprintf(
                __(
                    'Order completed internally because of an existing valid mandate at Mollie.',
                    'mollie-payments-for-woocommerce'
                )
            )
        );

        $this->logger->debug($this->gateway->id . ': Subscription switch completed, valid mandate for order #' . $order->get_id());

        return [
            'result' => 'success',
            'redirect' => $this->gateway->get_return_url($order),
        ];
    }

    /**
     * @param $order
     * @param string|null $customerId
     * @param $apiKey
     * @return bool
     * @throws ApiException
     */
    protected function processValidMandate($order, ?string $customerId, $apiKey): bool
    {
        $paymentObject = $this->paymentFactory->getPaymentObject(
            self::PAYMENT_METHOD_TYPE_PAYMENT
        );
        $paymentRequestData = $paymentObject->getPaymentRequestData($order, $customerId);
        $data = array_filter($paymentRequestData);
        $data = apply_filters('woocommerce_' . $this->gateway->id . '_args', $data, $order);

        $mandates = $this->apiHelper->getApiClient($apiKey)->customers->get($customerId)->mandates();
        $validMandate = false;
        foreach ($mandates as $mandate) {
            if ($mandate->status === 'valid') {
                $validMandate = true;
                $data['method'] = $mandate->method;
                break;
            }
        }
        return $validMandate;
    }

    protected function processSubscriptionSwitch(WC_Order $order, int $orderId, ?string $customerId, ?string $apiKey)
    {
        //
        // PROCESS SUBSCRIPTION SWITCH - If this is a subscription switch and customer has a valid mandate, process the order internally
        //
        try {
            $this->logger->debug($this->gateway->id . ': Subscription switch started, fetching mandate(s) for order #' . $orderId);
            $validMandate = $this->processValidMandate($order, $customerId, $apiKey);
            if ($validMandate) {
                return $this->subsSwitchCompleted($order);
            } else {
                $this->noValidMandateForSubsSwitchFailure($orderId);
            }
        } catch (ApiException $e) {
            if ($e->getField()) {
                throw $e;
            }
        }

        return ['result' => 'failure'];
    }

    /**
     * @param $orderId
     * @param $e
     * @param $paymentMethodId
     */
    protected function reportPaymentCreationFailure($orderId, $e, $paymentMethodId): void
    {
        $this->logger->debug(
            $paymentMethodId . ': Failed to create Mollie payment object for order ' . $orderId . ': ' . $e->getMessage(
            )
        );

        /* translators: Placeholder 1: Payment method title */
        $message = sprintf(__('Could not create %s payment.', 'mollie-payments-for-woocommerce'), $paymentMethodId);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message .= 'hii ' . $e->getMessage();
        }

        add_action('before_woocommerce_pay_form', static function () use ($message) {
            wc_print_notice($message, 'error');
        });
    }

    /**
     * @param $orderId
     * @param $apiKey
     * @param string|null $customerId
     * @param $paymentObject
     * @param $order
     * @throws ApiException
     */
    protected function saveSubscriptionMandateData(
        $orderId,
        $apiKey,
        ?string $customerId,
        $paymentObject,
        $order
    ): void {

        $dataHelper = $this->dataHelper;
        if ($dataHelper->isSubscription($orderId)) {
            $mandates = $this->apiHelper->getApiClient($apiKey)->customers->get($customerId)->mandates();
            if (!isset($mandates[0])) {
                return;
            }
            // madates are sorted by date, so the first one is the newest
            $mandate = $mandates[0];
            $customerId = $mandate->customerId;
            $mandateId = $mandate->id;
            $this->logger->debug(
                "Mollie Subscription in the order: customer id {$customerId} and mandate id {$mandateId} "
            );
            do_action($this->pluginId . '_after_mandate_created', $paymentObject, $order, $customerId, $mandateId);
        }
    }

    /**
     * @param $paymentObject
     * @param $order
     * @param $initialOrderStatus
     */
    protected function updatePaymentStatusForDelayedMethods($paymentObject, $order, $initialOrderStatus): void
    {
// Update initial order status for payment methods where the payment status will be delivered after a couple of days.
        // See: https://www.mollie.com/nl/docs/status#expiry-times-per-payment-method
        // Status is only updated if the new status is not the same as the default order status (pending)
        if (($paymentObject->method === Constants::BANKTRANSFER) || ($paymentObject->method === Constants::DIRECTDEBIT)) {
            // Don't change the status of the order if it's Partially Paid
            // This adds support for WooCommerce Deposits (by Webtomizer)
            // See https://github.com/mollie/WooCommerce/issues/138

            $order_status = $order->get_status();

            if ($order_status !== 'wc-partially-paid ') {
                $this->updateOrderStatus(
                    $order,
                    $initialOrderStatus,
                    __('Awaiting payment confirmation.', 'mollie-payments-for-woocommerce') . "\n"
                );
            }
        }
    }

    /**
     * @param $paymentObject
     * @param $orderId
     * @param $order
     */
    protected function reportPaymentSuccess($paymentObject, $orderId, $order, $paymentMethod): void
    {
        $paymentMethodTitle = $paymentMethod->getProperty('id');
        $this->logger->debug(
            $paymentMethodTitle . ': Mollie payment object ' . $paymentObject->id . ' (' . $paymentObject->mode . ') created for order ' . $orderId
        );
        $order->add_order_note(
            sprintf(
            /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
                __('%1$s payment started (%2$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $paymentObject->id . ($paymentObject->mode === 'test' ? (' - ' . __(
                    'test mode',
                    'mollie-payments-for-woocommerce'
                )) : '')
            )
        );

        $this->logger->debug(
            "For order " . $orderId . " redirect user to Mollie Checkout URL: " . $paymentObject->getCheckoutUrl()
        );
    }

    /**
     * @param $order
     * @param $orderId
     * @return bool
     */
    protected function needsSubscriptionSwitch($order, $orderId): bool
    {
        return ('0.00' === $order->get_total())
            && ($this->dataHelper->isWcSubscription($orderId) === true)
            && 0 !== $order->get_user_id()
            && (wcs_order_contains_switch($order));
    }

    /**
     * @param $exception
     * @return string[]
     */
    protected function paymentObjectFailure($exception): array
    {
        $this->logger->debug($exception->getMessage());
        return ['result' => 'failure'];
    }

    /**
     * @return mixed|void|null
     */
    protected function processInitialOrderStatus($paymentMethod)
    {
        $initialOrderStatus = $paymentMethod->getInitialOrderStatus();
        // Overwrite plugin-wide
        $initialOrderStatus = apply_filters(
            $this->pluginId . '_initial_order_status',
            $initialOrderStatus
        );
        // Overwrite gateway-wide
        return apply_filters(
            $this->pluginId . '_initial_order_status_' . $paymentMethod->getProperty('id'),
            $initialOrderStatus
        );
    }

    /**
     * Check if the exception is an outage, if so bail, log and inform user
     * @param ApiException $e
     * @return void
     * @throws ApiException
     */
    public function handleMollieOutage(ApiException $e): void
    {
        $isMollieOutage = $this->apiHelper->isMollieOutageException($e);
        if ($isMollieOutage) {
            $this->logger->debug(
                "Creating payment object: type Order failed due to a Mollie outage, stopping process. Check Mollie status at https://status.mollie.com/. {$e->getMessage()}"
            );

            throw new ApiException(
                __(
                    'Payment failed due to: Mollie is out of service. Please try again later.',
                    'mollie-payments-for-woocommerce'
                )
            );
        }
    }

    /**
     * Check if the exception is a fraud rejection, if so bail, log and inform user
     * @param ApiException $e
     * @return void
     * @throws ApiException
     */
    public function handleMollieFraudRejection(ApiException $e): void
    {
        $isMollieFraudException = $this->apiHelper->isMollieFraudException($e);
        if ($isMollieFraudException) {
            $this->logger->debug(
                "Creating payment object: The payment was declined due to suspected fraud, stopping process."
            );

            throw new ApiException(
                __(
                    'Payment failed due to:  The payment was declined due to suspected fraud.',
                    'mollie-payments-for-woocommerce'
                )
            );
        }
    }
}
