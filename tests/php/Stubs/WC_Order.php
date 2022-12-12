<?php

declare(strict_types=1);

class WC_Order
{
    public function get_id()
    {
    }

    public function get_order_number()
    {
    }

    public function get_items()
    {
    }

    public function get_total()
    {
    }

    public function payment_complete()
    {
    }

    public function get_currency()
    {
    }

    public function update_meta_data($fieldName, $value)
    {
    }

    public function save()
    {
    }

    public function add_order_note($note)
    {
    }

    /**
     * @param $fieldName
     * @param bool $single
     *
     * @return mixed
     */
    public function get_meta($fieldName, bool $single = true)
    {
    }

    public function update_status($newStatus, string $reason = null)
    {
    }

    public function get_checkout_order_received_url()
    {
    }

    public function get_view_order_url()
    {
    }

    public function get_cancel_order_url_raw()
    {
    }

    public function get_billing_phone()
    {
    }

    public function get_billing_first_name()
    {
    }

    public function get_billing_last_name()
    {
    }

    public function get_billing_country()
    {
    }

    public function get_billing_city()
    {
    }

    public function get_billing_address_1()
    {
    }

    public function get_shipping_country()
    {
    }

    public function get_customer_id()
    {
    }

    public function get_billing_email()
    {
    }

    public function set_transaction_id()
    {
    }
}
