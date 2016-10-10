<?php
class Mollie_WC_Gateway_Kbc extends Mollie_WC_Gateway_Abstract
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
        return Mollie_API_Object_Method::KBC;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('KBC/CBC Payment Button', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
