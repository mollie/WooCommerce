<?php

use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\WooCommerce\Payment\OrderItemsRefunder;

use Mollie\WooCommerceTests\TestCase;

class WC_Payment_Gateway extends WC_Settings_API
{
    public $id;
    public $enabled;

    public function __construct($id = 1, $enabled = 'yes')
    {
        $this->id = $id;
        $this->enabled = $enabled;
    }

    public function init_settings()
    {
    }
    public function get_return_url(){}
}

class WC_Settings_API
{
    public function get_option()
    {
    }

    public function process_admin_options()
    {
    }

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

class WooCommerce
{
    public $cart = null;
    public $session = null;
    public $customer = null;
    public $shipping = null;

    public function api_request_url()
    {
    }


}

class WC_Customer
{
    public function set_shipping_country()
    {
    }

    public function set_billing_country()
    {
    }

    public function set_shipping_postcode()
    {
    }

    public function set_shipping_city()
    {
    }

    public function get_shipping_country()
    {
    }

    public function get_billing_country()
    {
    }

}

class WC_Shipping
{
    public function get_packages()
    {
    }

    public function calculate_shipping()
    {
    }


}

class WC_Shipping_Rate
{
    public function get_id()
    {
    }

    public function get_label()
    {
    }

    public function get_cost()
    {
    }

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

class Api
{
    public function getApiClient()
    {

    }
}





class WC_HTTPS
{
    public static function force_https_url($string)
    {
    }
}

define('DAY_IN_SECONDS', 24 * 60 * 60);

class WC_Countries
{
    public function get_allowed_countries()
    {
    }

    public function get_shipping_countries()
    {
    }
}

class WC_Shipping_Zones
{
    public function get_zones()
    {
    }
}

class WC_Session
{
    public function set()
    {
    }
}

/**
 * This class is a partial mock to create an order
 * the order created is also partially mocked
 */
class Mollie_WC_Helper_PaymentFactory
{
    public function getPaymentObject($data)
    {
        $dataHelper = Plugin::getDataHelper();
        $refundLineItemsBuilder = new Mollie_WC_Payment_RefundLineItemsBuilder($dataHelper);
        $apiHelper = Plugin::getApiHelper();
        $settingsHelper = Plugin::getSettingsHelper();

        $orderItemsRefunded = new OrderItemsRefunder(
            $refundLineItemsBuilder,
            $dataHelper,
            $apiHelper->getApiClient($settingsHelper->isTestModeEnabled())->orders
        );
        return $this->pluginOrder($orderItemsRefunded,$data);
    }

    public function pluginOrder($orderItemsRefunded,$data)
    {
        $testCase = new TestCase();
        $mockBuilder = new PHPUnit_Framework_MockObject_MockBuilder($testCase, Mollie_WC_Payment_Order::class);
        $mock = $mockBuilder
            ->setConstructorArgs([$orderItemsRefunded, $data])
            ->setMethods(['createShippingAddress', 'createBillingAddress'])
            ->getMock();
        $mock->method('createShippingAddress')->willReturn('shippingAddressHere');
        $mock->method('createBillingAddress')->willReturn('billingAddressHere');
        return $mock;
    }
}

class Status
{

}
