<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_DirectDebit extends Mollie_WC_Gateway_Abstract {
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
	public function getMollieMethodId() {
		return PaymentMethod::DIRECTDEBIT;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		unset( $this->form_fields['display_logo'] );
		unset( $this->form_fields['description'] );

	}

	/**
	 * @return string
	 */
	public function getDefaultTitle() {
		return __( 'SEPA Direct Debit', 'mollie-payments-for-woocommerce' );
	}

	/**
	 * @return string
	 */
	protected function getSettingsDescription() {
		return __( 'SEPA Direct Debit is used for recurring payments with WooCommerce Subscriptions, and will not be shown in the WooCommerce checkout for regular payments! You also need to enable iDEAL and/or other "first" payment methods if you want to use SEPA Direct Debit.', 'mollie-payments-for-woocommerce' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultDescription() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool
	 */
	protected function paymentConfirmationAfterCoupleOfDays() {
		return true;
	}

	/**
	 * @param WC_Order                  $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param bool                      $admin_instructions
	 * @param bool                      $plain_text
	 *
	 * @return string|null
	 */
	protected function getInstructions( WC_Order $order, Mollie\Api\Resources\Payment $payment, $admin_instructions, $plain_text ) {
		if ( $payment->isPaid() && $payment->details ) {
			return sprintf(
			/* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
				__( 'Payment completed by <strong>%s</strong> (IBAN (last 4 digits): %s, BIC: %s)', 'mollie-payments-for-woocommerce' ),
				$payment->details->consumerName,
				substr($payment->details->consumerAccount, -4),
				$payment->details->consumerBic
			);
		}

		return parent::getInstructions( $order, $payment, $admin_instructions, $plain_text );
	}
}
