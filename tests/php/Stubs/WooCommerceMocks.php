<?php


namespace Mollie\WooCommerceTests\Stubs;


use Mollie\WooCommerceTests\TestCase;
use PHPUnit_Framework_Exception;
use WC_Countries;

class WooCommerceMocks extends TestCase
{
    /**
     *
     * @throws PHPUnit_Framework_Exception
     */
    public function wcOrder($id, $orderKey)
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'needs_payment'=> true,
                'get_id' => $id,
                'get_order_key' => $orderKey,
                'get_total' => '20',
                'get_items' => [$this->wcOrderItem()],
                'get_billing_first_name' => 'billingggivenName',
                'get_billing_last_name' => 'billingfamilyName',
                'get_billing_email' => 'billingemail',
                'get_shipping_first_name' => 'shippinggivenName',
                'get_shipping_last_name' => 'shippingfamilyName',
                'get_billing_address_1' => 'shippingstreetAndNumber',
                'get_billing_address_2' => 'billingstreetAdditional',
                'get_billing_postcode' => 'billingpostalCode',
                'get_billing_city' => 'billingcity',
                'get_billing_state' => 'billingregion',
                'get_billing_country' => 'billingcountry',
                'get_shipping_address_1' => 'shippingstreetAndNumber',
                'get_shipping_address_2' => 'shippingstreetAdditional',
                'get_shipping_postcode' => 'shippingpostalCode',
                'get_shipping_city' => 'shippingcity',
                'get_shipping_state' => 'shippingregion',
                'get_shipping_country' => 'shippingcountry',
                'get_shipping_methods' => false,
                'get_order_number' => 1,
                'get_payment_method' => 'mollie_wc_gateway_ideal',
                'get_currency' => 'EUR',

            ]
        );

        return $item;
    }

    public function wcOrderItem()
    {
        return $this->createConfiguredMock(
            'WC_Order_Item_Product', [
                'get_quantity' => 1,
                'get_total' => 20,
                'get_name' => 'productName']
        );
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcShipping()
    {
        $item = $this->createConfiguredMock(
            'WC_Shipping',
            [
                'calculate_shipping' => [
                    0 => [
                        'rates' => [
                            $this->wcShippingRate(
                                'flat_rate:1',
                                'Flat1',
                                '1.00'
                            ),
                            $this->wcShippingRate(
                                'flat_rate:4',
                                'Flat4',
                                '4.00'
                            )
                        ]
                    ]
                ]
            ]
        );

        return $item;
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcCountries()
    {
        $item = $this->createConfiguredMock(
            WC_Countries::class,
            [
                'get_allowed_countries' => ['IT' => 'Italy'],
                'get_shipping_countries' => ['IT' => 'Italy'],
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcCustomer($country = 'IT')
    {
        $item = $this->createConfiguredMock(
            'WC_Customer',
            [
                'get_shipping_country' => $country,
                'get_billing_country' => $country

            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcProduct()
    {
        $item = $this->createConfiguredMock(
            'WC_Product',
            [
                'get_price' => '1',
                'get_type' => 'simple',
                'needs_shipping' => true,
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce(
        $subtotal = 0,
        $shippingTotal = 0,
        $total = 0,
        $tax = 0,
        $country = 'IT',
        $cartNeedsShipping = true
    ) {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [
            ]
        );
        $item->cart = $this->wcCart($subtotal, $shippingTotal, $total, $tax, $cartNeedsShipping);
        $item->customer = $this->wcCustomer($country);
        $item->shipping = $this->wcShipping();
        $item->session = $this->wcSession();

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcCart($subtotal, $shippingTotal, $total, $tax, $needsShipping = true)
    {
        $item = $this->createConfiguredMock(
            'WC_Cart',
            [
                'needs_shipping' => $needsShipping,
                'get_subtotal' => $subtotal,
                'is_empty' => true,
                'get_shipping_total' => $shippingTotal,
                'add_to_cart' => '88888',
                'get_total_tax' => $tax,
                'get_total' => $total,
                'calculate_shipping' => null

            ]
        );

        return $item;
    }





    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcSession()
    {
        return $this->createConfiguredMock(
            'WC_Session',
            [
                'set' => null

            ]
        );
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcShippingRate($id, $label, $cost)
    {
        $item = $this->createConfiguredMock(
            'WC_Shipping_Rate',
            [
                'get_id' => $id,
                'get_label' => $label,
                'get_cost' => $cost

            ]
        );

        return $item;
    }
}
