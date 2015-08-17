<?php

if (!function_exists('is_order_received_page'))
{
    /**
     * Check if the current page is the order received page
     *
     * @since WooCommerce 2.3.3
     * @return bool
     */
    function is_order_received_page ()
    {
        global $wp;

        return (is_page(wc_get_page_id('checkout')) && isset($wp->query_vars['order-received'])) ? true : false;
    }
}

if (!function_exists('wc_date_format'))
{
    function wc_date_format ()
    {
        return apply_filters('woocommerce_date_format', get_option('date_format'));
    }
}

if (!function_exists('untrailingslashit'))
{
    /**
     * @since WooCommerce 2.2.0
     * @param string $string
     * @return string
     */
    function untrailingslashit ($string)
    {
        return rtrim($string, '/');
    }
}