<?php
class Mollie_WC_Gateway_PayPal extends Mollie_WC_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id       = 'mollie_paypal';
        $this->supports = array(
            'products',
            'refunds',
        );

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::PAYPAL;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('PayPal', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }

    /**
     * @param WC_Order                  $order
     * @param Mollie_API_Object_Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie_API_Object_Payment $payment, $admin_instructions, $plain_text)
    {
        $instructions = '';

        if ($payment->isPaid() && $payment->details)
        {
            $instructions .= sprintf(
                /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                __("Payment completed by <strong>%s</strong> - %s (PayPal transaction ID: %s)", 'woocommerce-mollie-payments'),
                $payment->details->consumerName,
                $payment->details->consumerAccount,
                $payment->details->paypalReference
            );
        }

        return $instructions;
    }
}
