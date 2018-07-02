<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Bancontact extends Mollie_WC_Gateway_AbstractSepaRecurring {
	/**
	 *
	 */
	public function __construct() {
		$this->supports = array (
			'products',
			'refunds',
		);

		// If there are still old MisterCash settings, copy them to Bancontact and remove MisterCash.
		$mistercash_settings = get_option( 'mollie_wc_gateway_mistercash_settings' );
		if ( $mistercash_settings != false ) {
			add_option( 'mollie_wc_gateway_bancontact_settings', $mistercash_settings );
			delete_option( 'mollie_wc_gateway_mistercash_settings' );
		}

		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function getMollieMethodId() {
		return PaymentMethod::BANCONTACT;
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle() {
		return __( 'Bancontact', 'mollie-payments-for-woocommerce' );
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
