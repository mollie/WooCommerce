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

if (!function_exists('mollieWooCommerceSession')) {
    function mollieWooCommerceSession()
    {
        return WC()->session;
    }
}
