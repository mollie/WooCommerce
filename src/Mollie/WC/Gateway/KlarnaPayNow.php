<?php

class Mollie_WC_Gateway_KlarnaPayNow extends Mollie_WC_Gateway_Abstract
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
		return 'klarnapaynow';
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle ()
	{
		return __('Klarna Pay now', 'mollie-payments-for-woocommerce');
	}

	/**
	 * @return string
	 */
	protected function getSettingsDescription() {
		return __('To accept payments via Klarna, all default WooCommerce checkout fields should be enabled and required.', 'mollie-payments-for-woocommerce');
	}

	/**
	 * @return string
	 */
	protected function getDefaultDescription ()
	{
		return '';
	}
}
