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

function mollieWooCommerceSession()
{
    return WC()->session;
}

/**
 * Get order ID in the correct way depending on WooCommerce version
 *
 * @param WC_Order $order
 * @return int
 */
function mollieWooCommerceOrderId(WC_Order $order)
{
    return version_compare(WC_VERSION, '3.0', '<')
        ? $order->id
        : $order->get_id();
}
/**
 * Get order key in the correct way depending on WooCommerce version
 *
 * @param WC_Order $order
 * @return string
 */
function mollieWooCommerceOrderKey(WC_Order $order)
{
    return version_compare(WC_VERSION, '3.0', '<')
        ? $order->order_key
        : $order->get_order_key();
}

