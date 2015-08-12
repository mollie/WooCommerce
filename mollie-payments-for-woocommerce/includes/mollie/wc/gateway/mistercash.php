<?php
class Mollie_WC_Gateway_MisterCash extends Mollie_WC_Gateway_Abstract
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
        return Mollie_API_Object_Method::MISTERCASH;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Bancontact / Mister Cash', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
