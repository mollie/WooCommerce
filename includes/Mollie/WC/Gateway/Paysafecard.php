<?php
class Mollie_WC_Gateway_Paysafecard extends Mollie_WC_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id = 'mollie_paysafecard';

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::PAYSAFECARD;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('paysafecard', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
