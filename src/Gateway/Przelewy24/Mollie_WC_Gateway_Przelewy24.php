<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Przelewy24;

use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class Mollie_WC_Gateway_Przelewy24 extends AbstractGateway
{
    /**
     *
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
        return PaymentMethod::PRZELEWY24;
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('Przelewy24', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription()
    {
        return __('To accept payments via Przelewy24, a customer email is required for every payment.', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription()
    {
        return '';
    }

    /**
     * @param WC_Order                  $order
     * @param Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions(WC_Order $order, Payment $payment, $admin_instructions, $plain_text)
    {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
            /* translators: Placeholder 1: customer billing email */
                __('Payment completed by <strong>%s</strong>.', 'mollie-payments-for-woocommerce'),
                $payment->details->billingEmail
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}
