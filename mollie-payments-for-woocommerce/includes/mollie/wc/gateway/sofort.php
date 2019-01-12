<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Sofort extends Mollie_WC_Gateway_AbstractSepaRecurring
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
        return PaymentMethod::SOFORT;
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
	protected function getSettingsDescription() {
		return '';
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
     * @param Mollie\Api\Resources\Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie\Api\Resources\Payment $payment, $admin_instructions, $plain_text)
    {
        if ($payment->isPaid() && $payment->details)
        {
	        return sprintf(
                /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%s</strong> (IBAN (last 4 digits): %s, BIC: %s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
		        substr($payment->details->consumerAccount, -4),
                $payment->details->consumerBic
            );
        }

	    return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}
