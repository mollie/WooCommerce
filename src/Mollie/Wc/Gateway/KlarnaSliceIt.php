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
		return __('Klarna Slice it', 'mollie-payments-for-woocommerce');
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
