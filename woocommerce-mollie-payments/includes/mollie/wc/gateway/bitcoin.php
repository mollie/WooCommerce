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
        return __('Bitcoin', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
