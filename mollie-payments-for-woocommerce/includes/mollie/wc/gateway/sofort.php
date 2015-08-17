<?php
class Mollie_WC_Gateway_Sofort extends Mollie_WC_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
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
        return Mollie_API_Object_Method::SOFORT;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('SOFORT Banking', 'mollie-payments-for-woocommerce');
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
                /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%s</strong> (IBAN: %s, BIC: %s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                implode(' ', str_split($payment->details->consumerAccount, 4)),
                $payment->details->consumerBic
            );
        }

        return $instructions;
    }
}
