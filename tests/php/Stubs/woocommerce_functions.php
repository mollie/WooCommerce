<?php

use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\WooCommerce\Payment\OrderItemsRefunder;

use Mollie\WooCommerceTests\TestCase;

class Status
{
}

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


class Data
{
    public function getWcOrder()
    {
    }

    public function getOrderStatus()
    {
    }

    public function isWcSubscription($order)
    {
        if ($order == 'subs') {
            return true;
        }
        return false;
    }

    public function getUserMollieCustomerId()
    {
    }

    public function setUserMollieCustomerId()
    {
    }

    public function getOrderCurrency()
    {
        return 'EUR';
    }

    public function formatCurrencyValue($value)
    {
        return $value;
    }

    public function isSubscription()
    {
        return false;
    }
}



