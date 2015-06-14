<?php
class WC_Mollie_Gateway_Paysafecard extends WC_Mollie_Gateway_Abstract
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
