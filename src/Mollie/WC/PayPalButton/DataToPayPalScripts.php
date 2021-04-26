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
        $paypalSettings = get_option('mollie_wc_gateway_paypal_settings', false);
        $minAmount = 0;
        if ($paypalSettings) {
            $minAmount
                = isset($paypalSettings['mollie_paypal_button_minimum_amount'])
            && $paypalSettings['mollie_paypal_button_minimum_amount'] > 0
                ? $paypalSettings['mollie_paypal_button_minimum_amount'] : 0;
        }

        if (is_product()) {
            return $this->dataForProductPage($minAmount);
        }
        if (is_cart()) {
            return $this->dataForCartPage($minAmount);
        }
        return [];
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
        $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
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
