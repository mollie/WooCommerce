<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

class DataToAppleButtonScripts
{
    /**
     * Sets the appropriate data to send to ApplePay script
     * Data differs between product page and cart page
     *
     * @return array
     */
    public function applePayScriptData(bool $isBlock = false): array
    {
        $base_location = wc_get_base_location();
        $shopCountryCode = $base_location['country'];
        $currencyCode = get_woocommerce_currency();
        $totalLabel = get_bloginfo('name');
        if (is_product()) {
            return $this->dataForProductPage(
                $shopCountryCode,
                $currencyCode,
                $totalLabel
            );
        }
        if (is_cart() || $isBlock) {
            return $this->dataForCartPage(
                $shopCountryCode,
                $currencyCode,
                $totalLabel
            );
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
        if (
            !wc_shipping_enabled()
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
     * @param $shopCountryCode
     * @param $currencyCode
     * @param $totalLabel
     *
     * @return array
     */
    protected function dataForProductPage(
        $shopCountryCode,
        $currencyCode,
        $totalLabel
    ) {

        $product = wc_get_product(get_the_id());
        if (!$product) {
            return [];
        }
        $isVariation = false;
        if ($product->get_type() === 'variable' || $product->get_type() === 'variable-subscription') {
            $isVariation = true;
        }
        $productNeedShipping = $this->checkIfNeedShipping($product);
        $productId = get_the_id();
        $productPrice = $product->get_price();
        $productStock = $product->get_stock_status();

        return [
            'product' => [
                'needShipping' => $productNeedShipping,
                'id' => $productId,
                'price' => $productPrice,
                'isVariation' => $isVariation,
                'stock' => $productStock,
            ],
            'shop' => [
                'countryCode' => $shopCountryCode,
                'currencyCode' => $currencyCode,
                'totalLabel' => $totalLabel,
            ],
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ];
    }

    /**
     * @param $shopCountryCode
     * @param $currencyCode
     * @param $totalLabel
     *
     * @return array
     */
    protected function dataForCartPage(
        $shopCountryCode,
        $currencyCode,
        $totalLabel
    ) {

        $cart = WC()->cart;
        $nonce = wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
        $buttonMarkup =
            '<div id="mollie-applepayDirect-button">'
            . $nonce
            . '</div>';
        return [
            'product' => [
                'needShipping' => $cart->needs_shipping(),
                'subtotal' => $cart->get_subtotal(),
            ],
            'shop' => [
                'countryCode' => $shopCountryCode,
                'currencyCode' => $currencyCode,
                'totalLabel' => $totalLabel,
            ],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'buttonMarkup' => $buttonMarkup,
        ];
    }
}
