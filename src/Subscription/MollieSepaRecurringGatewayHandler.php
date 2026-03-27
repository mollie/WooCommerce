<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Subscription;

use DateInterval;
use DateTime;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\SequenceType;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\Psr\Log\LoggerInterface as Logger;
class MollieSepaRecurringGatewayHandler extends \Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler
{
    const WAITING_CONFIRMATION_PERIOD_DAYS = '21';
    protected $recurringMollieMethod = null;
    protected $dataHelper;
    /**
     * AbstractSepaRecurring constructor.
     */
    public function __construct(PaymentMethodI $directDebitPaymentMethod, PaymentMethodI $paymentMethod, OrderInstructionsManager $orderInstructionsService, MollieOrderService $mollieOrderService, Data $dataService, Logger $logger, NoticeInterface $notice, HttpResponse $httpResponse, Settings $settingsHelper, MollieObject $mollieObject, PaymentFactory $paymentFactory, string $pluginId, Api $apiHelper)
    {
        parent::__construct($paymentMethod, $orderInstructionsService, $mollieOrderService, $dataService, $logger, $notice, $httpResponse, $settingsHelper, $mollieObject, $paymentFactory, $pluginId, $apiHelper);
        $directDebitSettings = get_option('mollie_wc_gateway_directdebit_settings');
        if (isset($directDebitSettings['enabled']) && $directDebitSettings['enabled'] === 'yes') {
            $this->recurringMollieMethod = $directDebitPaymentMethod;
        }
        return $this;
    }
    /**
     * @return string
     */
    protected function getRecurringMollieMethodId()
    {
        $result = null;
        if ($this->recurringMollieMethod) {
            $result = $this->recurringMollieMethod->getProperty('id');
        }
        return $result;
    }
    /**
     * @return string
     */
    protected function getRecurringMollieMethodTitle()
    {
        $result = null;
        if ($this->recurringMollieMethod) {
            $result = $this->recurringMollieMethod->getProperty('title');
        }
        return $result;
    }
    /**
     * @param $renewal_order
     * @param $initial_order_status
     * @param $payment
     */
    protected function updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment)
    {
        $this->mollieOrderService->updateOrderStatus($renewal_order, $initial_order_status, sprintf(__("Awaiting payment confirmation.", 'mollie-payments-for-woocommerce') . "\n", self::WAITING_CONFIRMATION_PERIOD_DAYS));
        $payment_method_title = $this->getPaymentMethodTitle($payment);
        $renewal_order->add_order_note(sprintf(
            /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%1$s payment started (%2$s).', 'mollie-payments-for-woocommerce'),
            $payment_method_title,
            $payment->id . ($payment->mode === 'test' ? ' - ' . __('test mode', 'mollie-payments-for-woocommerce') : '')
        ));
        $this->addPendingPaymentOrder($renewal_order);
    }
    /**
     * @return bool
     */
    public function paymentConfirmationAfterCoupleOfDays(): bool
    {
        return \true;
    }
    /**
     * @param $renewal_order
     */
    protected function addPendingPaymentOrder($renewal_order)
    {
        global $wpdb;
        $confirmationDate = new DateTime();
        $period = 'P' . self::WAITING_CONFIRMATION_PERIOD_DAYS . 'D';
        $confirmationDate->add(new DateInterval($period));
        $wpdb->insert($wpdb->mollie_pending_payment, ['post_id' => $renewal_order->get_id(), 'expired_time' => $confirmationDate->getTimestamp()]);
    }
    /**
     * @param Payment $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {
        $payment_method_title = $this->paymentMethod->getProperty('title');
        $orderId = isset($payment->metadata) ? $payment->metadata->order_id : \false;
        if ($orderId && $this->dataService->isWcSubscription($orderId) && $payment->method === $this->getRecurringMollieMethodId()) {
            $payment_method_title = $this->getRecurringMollieMethodTitle();
        }
        return $payment_method_title;
    }
    /**
     * @param $order
     * @param $payment
     */
    public function handlePaidOrderWebhook($order, $payment)
    {
        $orderId = $order->get_id();
        // Duplicate webhook call
        if ($this->dataService->isWcSubscription($orderId) && isset($payment->sequenceType) && $payment->sequenceType === SequenceType::SEQUENCETYPE_RECURRING) {
            $payment_method_title = $this->getPaymentMethodTitle($payment);
            $isTestMode = $payment->mode === 'test';
            $paymentMessage = $payment->id . ($isTestMode ? ' - ' . __('test mode', 'mollie-payments-for-woocommerce') : '');
            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed using %1$s payment (%2$s).', 'mollie-payments-for-woocommerce'),
                $payment_method_title,
                $paymentMessage
            ));
            try {
                $payment_object = $this->paymentFactory->getPaymentObject($payment);
            } catch (ApiException $exception) {
                $this->logger->debug($exception->getMessage());
                return;
            }
            $payment_object->deleteSubscriptionOrderFromPendingPaymentQueue($order);
            return;
        }
        parent::handlePaidOrderWebhook($order, $payment);
    }
}
