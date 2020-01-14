<?php

function wc_string_to_bool($string)
{
    return is_bool($string) ? $string : ('yes' === strtolower(
            $string
        ) || 1 === $string || 'true' === strtolower($string) || '1' === $string);
}

/**
 * Converts a bool to a 'yes' or 'no'.
 *
 * @param bool $bool String to convert.
 * @return string
 * @since 3.0.0
 */
function wc_bool_to_string($bool)
{
    if (!is_bool($bool)) {
        $bool = wc_string_to_bool($bool);
    }
    return true === $bool ? 'yes' : 'no';
}

class WooCommerce
{

}

class WC_Payment_Gateway
{
    public $id;
    public function __construct($id = 1)
    {
        $this->id = $id;
    }
}

class WC_Order
{
    public function get_order_key()
    {
    }
    public function get_id()
    {
    }
    public function add_order_note($note)
    {
    }
}

class WC_Order_Item
{
    public function get_quantity()
    {
    }

    public function get_total()
    {
    }

    public function get_total_tax()
    {
    }

    public function get_meta()
    {
    }
}
