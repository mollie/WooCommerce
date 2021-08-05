<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Subscription;

use DateInterval;
use DateTime;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Types\SequenceType;
use Mollie\WooCommerce\Gateway\DirectDebit\Mollie_WC_Gateway_DirectDebit;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;

abstract class AbstractSepaRecurring extends AbstractSubscription
{

    const WAITING_CONFIRMATION_PERIOD_DAYS = '21';

    protected $recurringMollieMethod = null;

    /**
     * AbstractSepaRecurring constructor.
     */
    public function __construct(
        IconFactory $iconFactory,
        PaymentService $paymentService,
        SurchargeService $surchargeService,
        MollieOrderService $mollieOrderService,
        Logger $logger,
        NoticeInterface $notice
    ) {

        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice
        );
        $directDebit = new Mollie_WC_Gateway_DirectDebit(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice
        );
        if ($directDebit->enabled == 'yes') {
            $this->initSubscriptionSupport();
            $this->recurringMollieMethod = $directDebit;
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
            $result = $this->recurringMollieMethod->getMollieMethodId();
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
            $result = $this->recurringMollieMethod->getDefaultTitle();
        }

        return $result;
    }

    /**
     * @param $renewal_order
     * @param $initial_order_status
     * @param $payment
     */
    protected function _updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment)
    {
        $this->updateOrderStatus(
            $renewal_order,
            $initial_order_status,
            sprintf(
                __("Awaiting payment confirmation.", 'mollie-payments-for-woocommerce') . "\n",
                self::WAITING_CONFIRMATION_PERIOD_DAYS
            )
        );

        $payment_method_title = $this->getPaymentMethodTitle($payment);

        $renewal_order->add_order_note(sprintf(
        /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%1$s payment started (%2$s).', 'mollie-payments-for-woocommerce'),
            $payment_method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));

        $this->addPendingPaymentOrder($renewal_order);
    }

    /**
     * @return bool
     */
    protected function paymentConfirmationAfterCoupleOfDays()
    {
        return true;
    }

    /**
     * @param $renewal_order
     */
    protected function addPendingPaymentOrder($renewal_order)
    {
        global $wpdb;

        $confirmationDate = new DateTime();
        $period = 'P'.self::WAITING_CONFIRMATION_PERIOD_DAYS . 'D';
        $confirmationDate->add(new DateInterval($period));
        $wpdb->insert(
            $wpdb->mollie_pending_payment,
            [
                'post_id' => $renewal_order->get_id(),
                'expired_time' => $confirmationDate->getTimestamp(),
            ]
        );
    }

    /**
     * @param null $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {
        $payment_method_title = parent::getPaymentMethodTitle($payment);
        $orderId = isset($payment->metadata) ? $payment->metadata->order_id : false;
        if ($orderId && Plugin::getDataHelper()->isWcSubscription($orderId) && $payment->method == $this->getRecurringMollieMethodId()) {
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
        if (Plugin::getDataHelper()->isWcSubscription($orderId)
            && isset($payment->sequenceType)
            && $payment->sequenceType == SequenceType::SEQUENCETYPE_RECURRING
        ) {
            $payment_method_title = $this->getPaymentMethodTitle($payment);

            $isTestMode = $payment->mode === 'test';
            $paymentMessage = $payment->id . (
                $isTestMode
                    ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce'))
                    : ''
                );
            $order->add_order_note(
                sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __(
                        'Order completed using %1$s payment (%2$s).',
                        'mollie-payments-for-woocommerce'
                    ),
                    $payment_method_title,
                    $paymentMessage
                )
            );

            try {
                $payment_object = Plugin::getPaymentFactoryHelper()->getPaymentObject(
                    $payment
                );
            } catch (ApiException $exception) {
                Plugin::debug($exception->getMessage());
                return;
            }

            $payment_object->deleteSubscriptionOrderFromPendingPaymentQueue($order);
            return;
        }

        parent::handlePaidOrderWebhook($order, $payment);
    }
}
