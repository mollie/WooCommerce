<?php

use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentMethod;

/**
 * Class applepay
 */
class Mollie_WC_Gateway_Applepay extends Mollie_WC_Gateway_Abstract
{
    /**
     * Mollie_WC_Gateway_Applepay constructor.
     */
    public function __construct()
    {
        $this->supports = [
            'products',
            'refunds',
        ];

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId()
    {
        return PaymentMethod::APPLEPAY;
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('Apple Pay', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription()
    {
        return __('To accept payments via Apple Pay', 'mollie-payments-for-woocommerce');
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
        Mollie\Api\Resources\Payment $payment,
        $admin_instructions,
        $plain_text
    ) {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
                __(
                /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                    "Payment completed by <strong>%1$s</strong> - %2$s (Apple Pay transaction ID: %3$s)",
                    'mollie-payments-for-woocommerce'
                ),
                $payment->details->consumerName,
                $payment->details->consumerAccount,
                $payment->details->paypalReference
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}
