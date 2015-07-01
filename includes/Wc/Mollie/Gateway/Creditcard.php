<?php
class WC_Mollie_Gateway_Creditcard extends WC_Mollie_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id       = 'mollie_creditcard';
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
        return Mollie_API_Object_Method::CREDITCARD;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('Credit card', 'woocommerce-mollie-payments');
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
                /* translators: Placeholder 1: card holder */
                __('Payment completed by <strong>%s</strong>', 'woocommerce-mollie-payments'),
                $payment->details->cardHolder
            );
        }

        return $instructions;
    }
}
