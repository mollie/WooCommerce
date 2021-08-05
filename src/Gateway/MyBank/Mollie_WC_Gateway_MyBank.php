<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\MyBank;

use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

/**
 * Class Mollie_WC_Gateway_MyBank
 */
class Mollie_WC_Gateway_MyBank extends AbstractGateway
{
    /**
     * Mollie_WC_Gateway_MyBank constructor.
     */
    public function __construct(
        IconFactory $iconFactory,
        PaymentService $paymentService,
        SurchargeService $surchargeService,
        MollieOrderService $mollieOrderService,
        Logger $logger,
        NoticeInterface $notice
    ) {

        $this->supports = [
            'products',
            'refunds',
        ];

        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice
        );
    }

    /**
     * @return string
     */
    public function getMollieMethodId()
    {
        return 'mybank';
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('MyBank', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription()
    {
        return __('To accept payments via MyBank', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription()
    {
        return '';
    }

    /**
     * Get Order Instructions
     *
     * @param WC_Order $order
     * @param Payment $payment
     * @param bool $admin_instructions
     * @param bool $plain_text
     * @return string|null
     */
    protected function getInstructions(
        WC_Order $order,
        Payment $payment,
        $admin_instructions,
        $plain_text
    ) {

        if ($payment->isPaid() && $payment->details) {
            return sprintf(
                __(
                    /* translators: Placeholder 1: Mollie_WC_Gateway_MyBank consumer name, placeholder 2: Consumer Account number */
                    'Payment completed by <strong>%1$s</strong> - %2$s',
                    'mollie-payments-for-woocommerce'
                ),
                $payment->details->consumerName,
                $payment->details->consumerAccount
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}
