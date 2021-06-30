<?php

namespace Mollie\WooCommerce\Gateway\EPS;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Subscription\AbstractSepaRecurring;

class Mollie_WC_Gateway_EPS extends AbstractSepaRecurring {
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
