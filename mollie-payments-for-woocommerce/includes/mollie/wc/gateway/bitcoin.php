<?php
class Mollie_WC_Gateway_Bitcoin extends Mollie_WC_Gateway_Abstract
{
    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::BITCOIN;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Bitcoin', 'mollie-payments-for-woocommerce');
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
