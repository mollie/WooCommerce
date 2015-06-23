<?php
class WC_Mollie_Gateway_BankTransfer extends WC_Mollie_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id       = 'mollie_banktransfer';
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
        return Mollie_API_Object_Method::BANKTRANSFER;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('Bank Transfer', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function getInitialStatus ()
    {
        return 'on-hold';
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    protected function orderNeedsPayment (WC_Order $order)
    {
        $onHold = method_exists($order, 'get_status') ? $order->get_status() == 'on-hold' : $order->status == 'on-hold';

        // needs_payment() searches using valid_statusses, but does not include on-hold status, so we add it here.
        return parent::orderNeedsPayment($order) || $onHold;
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

        if (!$payment->details)
        {
            return null;
        }

        if ($payment->isPaid())
        {
            $instructions .= sprintf(
                __('Payment completed by <strong>%s</strong> (IBAN: %s, BIC: %s)'),
                $payment->details->consumerName,
                implode(' ', str_split($payment->details->consumerAccount, 4)),
                $payment->details->consumerBic
            );
        }
        elseif ($order->has_status('on-hold'))
        {
            if (!$admin_instructions)
            {
                $instructions .= __('Please complete your payment by transferring the total amount to the following bank account:', 'woocommerce-mollie-payments') . "\n\n\n";
            }

            $instructions .= sprintf(__('IBAN: %s', 'woocommerce-mollie-payments'), implode(' ', str_split($payment->details->bankAccount, 4))) . "\n";
            $instructions .= sprintf(__('Bank account: %s', 'woocommerce-mollie-payments'), $payment->details->bankName) . "\n";
            $instructions .= sprintf(__('BIC: %s', 'woocommerce-mollie-payments'), $payment->details->bankBic) . "\n";

            if ($admin_instructions)
            {
                $instructions .= sprintf(__('Payment reference: %s', 'woocommerce-mollie-payments'), $payment->details->transferReference) . "\n";
            }
            else
            {
                $instructions .= sprintf(__('Please provide the payment reference <strong>%s</strong>', 'woocommerce-mollie-payments'), $payment->details->transferReference) . "\n";
            }
        }

        return $instructions;
    }
}
