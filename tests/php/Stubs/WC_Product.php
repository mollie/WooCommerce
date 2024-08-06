<?php

namespace Mollie\WooCommerceTests\Stubs;


class WC_Product
{
    public function getProduct($productId) {
        return wc_get_product($productId);
    }
    public function get_id()
    {
        return 1;
    }

    public function get_type()
    {
    }

    public function get_price()
    {
    }

    public function get_sku()
    {
        return 'sku';
    }

    public function needs_shipping()
    {
    }

    public function is_taxable()
    {
    }

    public function is_type($type){
    }

    public function get_stock_status()
    {
    }

}
