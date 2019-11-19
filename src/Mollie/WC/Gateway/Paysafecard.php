<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Paysafecard extends Mollie_WC_Gateway_Abstract
{
    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return PaymentMethod::PAYSAFECARD;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('paysafecard', 'mollie-payments-for-woocommerce');
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
}
