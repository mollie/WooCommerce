<?php

function wcs_get_subscriptions($subs)
{
}

/**
 * Main function for returning subscriptions. Wrapper for the wc_get_order() method.
 *
 * @param mixed $the_subscription Post object or post ID of the order.
 * @return WC_Subscription|false The subscription object, or false if it cannot be found.
 * @since  1.0.0 - Migrated from WooCommerce Subscriptions v2.0
 */
function wcs_get_subscription($the_subscription)
{
}

function wcs_order_contains_renewal($order)
{
}

function wcs_get_subscriptions_for_renewal_order($order)
{
}

class WC_Product_Subscription_Variation
{
}

class WC_Subscriptions_Manager
{
}

class WC_Product_Variable_Subscription extends \WC_Product_Variable
{
}

class WC_Subscription extends \WC_Order
{
    public function get_parent()
    {
        
    }
}

class WC_Subscriptions_Admin
{
    public static $option_prefix;
}

