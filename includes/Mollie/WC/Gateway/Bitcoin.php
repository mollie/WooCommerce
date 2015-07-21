<?php
class Mollie_WC_Gateway_Bitcoin extends Mollie_WC_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id = 'mollie_bitcoin';

        parent::__construct();
    }

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
    protected function getDefaultTitle ()
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
