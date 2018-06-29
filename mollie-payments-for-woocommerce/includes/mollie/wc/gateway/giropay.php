<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Giropay extends Mollie_WC_Gateway_Abstract {
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
		return PaymentMethod::GIROPAY;
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle() {
		return __( 'Giropay', 'mollie-payments-for-woocommerce' );
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
