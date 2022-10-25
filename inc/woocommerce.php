<?php
if (!function_exists('has_block')) {
    /**
     * Check if the current page has block
     *
     * @since WooCommerce 5.0
     * @return bool
     */
    function has_block($blockName)
    {
        return false;
    }
}

if (!function_exists('is_order_received_page')) {
    /**
     * Check if the current page is the order received page
     *
     * @since WooCommerce 2.3.3
     * @return bool
     */
    function is_order_received_page()
    {
        global $wp;

        return (is_page(wc_get_page_id('checkout')) && isset($wp->query_vars['order-received'])) ? true : false;
    }
}

if (!function_exists('untrailingslashit')) {
    /**
     * @since WooCommerce 2.2.0
     * @param string $string
     * @return string
     */
    function untrailingslashit($string)
    {
        return rtrim($string, '/');
    }
}

if (!function_exists('as_unschedule_action')) {
    /**
     * @since WooCommerce 3.0.0
     * @param string $hook
     * @param array $args
     * @param string $group
     * @return bool
     */
    function as_unschedule_action($hook, $args = [], $group = '')
    {
        return 0;
    }
}

function mollieWooCommerceSession()
{
    return WC()->session;
}

/**
 * Mimics wc_string_to_bool
 * @param $string
 *
 * @return bool
 */
function mollieWooCommerceStringToBoolOption($string)
{
    return is_bool($string) ? $string : ('yes' === strtolower(
        $string
    ) || 1 === $string || 'true' === strtolower($string) || '1' === $string);
}
