<?php
class Mollie_WC_Gateway_Belfius extends Mollie_WC_Gateway_Abstract
{
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
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::BELFIUS;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Belfius Direct Net', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
