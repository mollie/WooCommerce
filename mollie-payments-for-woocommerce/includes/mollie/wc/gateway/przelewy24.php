<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Przelewy24 extends Mollie_WC_Gateway_Abstract
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
		return PaymentMethod::PRZELEWY24;
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle ()
	{
		return __('Przelewy24', 'mollie-payments-for-woocommerce');
	}

	/**
	 * @return string
	 */
	protected function getSettingsDescription() {
		return __('To accept payments via Przelewy24, a customer email is required for every payment.', 'mollie-payments-for-woocommerce');
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
			/* translators: Placeholder 1: customer billing email */
				__('Payment completed by <strong>%s</strong>.', 'mollie-payments-for-woocommerce'),
				$payment->details->billingEmail
			);
		}

		return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
	}
}
