<?php


class Mollie_WC_PayPalButton_CustomShippingMethod extends WC_Shipping_Method
{
    /**
     * Constructor for the PayPal shipping method
     *
     * @access public
     * @return void
     */
    public function __construct($instance_id = 0)
    {
        $this->id = 'PayPalButtonFixedShipping';
        $this->instance_id           = absint( $instance_id );
        $this->method_title = __('PayPal Button Fixed Shipping');
        $this->method_description = __('Description of your shipping method');

        $this->enabled = "yes";
        $this->title = "PayPal Button Fixed Shipping";

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
        $paypalSettings = get_option('mollie_wc_gateway_paypal_settings');
        $cost = $paypalSettings['mollie_paypal_button_fixed_shipping_amount'];
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost? $cost: 0,
        );

        $this->add_rate($rate);
    }
}




