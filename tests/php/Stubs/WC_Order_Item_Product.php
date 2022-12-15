<?php

declare(strict_types=1);

class WC_Order_Item_Product extends WC_Order_Item
{
    /**
     * @return WC_Product|bool
     */
    public function get_product()
    {
    }
    public function get_item_quantity()
    {
    }

    public function get_name()
    {
        return 'productName';
    }

    public function get_id()
    {
    }

    public function get_amount()
    {
    }
}
