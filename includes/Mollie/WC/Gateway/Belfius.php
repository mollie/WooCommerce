<?php
class Mollie_WC_Gateway_Belfius extends Mollie_WC_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id       = 'mollie_belfius';
        $this->supports = array(
            'products',
            'refunds',
        );

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::BELFIUS;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('Belfius Direct Net', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
