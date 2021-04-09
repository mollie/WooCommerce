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
        $paypalSettings = get_option('mollie_wc_gateway_paypal_settings');
        $minAmount = $paypalSettings['mollie_paypal_button_minimum_amount'];
        if (is_product()) {
            return $this->dataForProductPage($minAmount);
        }
        if (is_cart()) {
            return $this->dataForCartPage($minAmount);
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
     * @param $minAmount
     *
     * @return array|bool
     */
    protected function dataForProductPage($minAmount)
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
                'minFee' =>$minAmount
            ],
            'ajaxUrl' => admin_url('admin-ajax.php')
        ];
    }

    /**
     *
     * @param $minAmount
     *
     * @return array
     */
    protected function dataForCartPage($minAmount)
    {
        $cart = WC()->cart;
        return [
            'product' => [
                'needShipping' => $cart->needs_shipping(),
                'minFee' =>$minAmount

            ],
            'ajaxUrl' => admin_url('admin-ajax.php')
        ];
    }

}
