<?php

class Mollie_WC_PayPalButton_DataToPayPalScripts
{
    /**
     * Sets the appropriate data to send to PayPal script
     * Data differs between product page and cart page
     *
     * @return array|bool
     */
    public function paypalbuttonScriptData()
    {
        if (is_product()) {
            return $this->dataForProductPage();
        }
        if (is_cart()) {
            return $this->dataForCartPage();
        }
        return [];
    }

    /**
     * Check if the product needs shipping
     *
     * @param $product
     *
     * @return bool
     */
    protected function checkIfNeedShipping($product)
    {
        if (!wc_shipping_enabled()
            || 0 === wc_get_shipping_method_count(
                true
            )
        ) {
            return false;
        }
        $needs_shipping = false;

        if ($product->needs_shipping()) {
            $needs_shipping = true;
        }

        return $needs_shipping;
    }

    /**
     *
     * @return array|bool
     */
    protected function dataForProductPage()
    {
        $product = wc_get_product(get_the_id());
        if (!$product) {
            return false;
        }
        $isVariation = false;
        if ($product->get_type() === 'variable') {
            $isVariation = true;
        }
        $productNeedShipping = $this->checkIfNeedShipping($product);
        $productId = get_the_id();
        $productPrice = $product->get_price();

        return [
            'product' => [
                'needShipping' => $productNeedShipping,
                'id' => $productId,
                'price' => $productPrice,
                'isVariation' => $isVariation,
            ],
            'ajaxUrl' => admin_url('admin-ajax.php')
        ];
    }

    /**
     *
     * @return array
     */
    protected function dataForCartPage()
    {
        return [

            'ajaxUrl' => admin_url('admin-ajax.php')
        ];
    }

}
