<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_KlarnaSliceIt extends Mollie_WC_Gateway_Abstract
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
		return PaymentMethod::KLARNA_SLICE_IT;
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle ()
	{
		return __('Klarna Slice It', 'mollie-payments-for-woocommerce');
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
	 * @param Mollie\Api\Resources\Order $mollie_order
	 * @param bool                      $admin_instructions
	 * @param bool                      $plain_text
	 * @return string|null
	 */
	protected function getInstructions (WC_Order $order, Mollie\Api\Resources\Order $mollie_order, $admin_instructions, $plain_text)
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
}
