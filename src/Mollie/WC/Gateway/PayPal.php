<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_PayPal extends Mollie_WC_Gateway_Abstract
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
     * @inheritDoc
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->includePayPalButton();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return PaymentMethod::PAYPAL;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('PayPal', 'mollie-payments-for-woocommerce');
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
                /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                __("Payment completed by <strong>%s</strong> - %s (PayPal transaction ID: %s)", 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                $payment->details->consumerAccount,
                $payment->details->paypalReference
            );
        }

	    return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);

    }


    protected function includePayPalButton()
    {
        $fields = include Mollie_WC_Plugin::getPluginPath(
            '/inc/settings/mollie_paypal_button_enabler.php'
        );

        $this->form_fields = array_merge($this->form_fields, $fields);
    }
}
