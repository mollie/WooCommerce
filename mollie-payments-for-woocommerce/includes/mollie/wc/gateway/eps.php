<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_EPS extends Mollie_WC_Gateway_AbstractSepaRecurring {
	/**
	 *
	 */
	public function __construct() {
		$this->supports = array (
			'products',
			'refunds',
		);

		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function getMollieMethodId() {
		return PaymentMethod::EPS;
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle() {
		return __( 'EPS', 'mollie-payments-for-woocommerce' );
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
	protected function getDefaultDescription() {
		return '';
	}

}
