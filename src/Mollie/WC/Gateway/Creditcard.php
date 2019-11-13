<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Creditcard extends Mollie_WC_Gateway_AbstractSubscription
{
    /**
     *
     */
    public function __construct ()
    {
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->initSubscriptionSupport();

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return PaymentMethod::CREDITCARD;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Credit card', 'mollie-payments-for-woocommerce');
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
                /* translators: Placeholder 1: card holder */
                __('Payment completed by <strong>%s</strong>', 'mollie-payments-for-woocommerce'),
                $payment->details->cardHolder
            );
        }

	    return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);

    }
}
