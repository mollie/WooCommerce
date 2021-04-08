<?php


class Mollie_WC_PayPalButton_CustomShippingMethod extends WC_Shipping_Method
{
    public $shippingCost;
    /**
     * Constructor for the PayPal shipping method
     *
     * @access public
     * @return void
     */
    public function __construct($shippingCost = 0, $instance_id = 0)
    {
        $this->shippingCost = $shippingCost;
        $this->id = 'PayPalButtonFixedShipping';
        $this->instance_id           = absint( $instance_id );
        $this->method_title = __('PayPal Button Fixed Shipping', 'mollie-payments-for-woocommerce');
        $this->method_description = __('Description of your shipping method', 'mollie-payments-for-woocommerce');

        $this->enabled = "yes";
        $this->title = __('PayPal Button Fixed Shipping', 'mollie-payments-for-woocommerce');

        $this->init();
    }

    function init()
    {
        $this->init_form_fields(
        );
        $this->init_settings(
        );
        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            array($this, 'process_admin_options')
        );
    }

    /**
     * calculate_shipping function.
     *
     * @access public
     *
     * @param mixed $package
     *
     * @return void
     */
    public function calculate_shipping($package = [])
    {
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->shippingCost,
        );

        $this->add_rate($rate);
    }
}




