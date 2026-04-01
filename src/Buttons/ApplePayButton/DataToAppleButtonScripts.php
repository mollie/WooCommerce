<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

class DataToAppleButtonScripts
{
    /**
     * Sets the appropriate data to send to ApplePay script
     * Data differs between product page and cart page
     *
     * @return array<mixed>
     */
    public function applePayScriptData(bool $isBlock = false): array
    {
        if (is_admin()) {
            return [];
        }
        $base_location = wc_get_base_location();
        $shopCountryCode = (string) ($base_location['country'] ?? '');
        $currencyCode = get_woocommerce_currency();
        $totalLabel = get_bloginfo('name');
        if (is_product()) {
            return $this->dataForProductPage(
                $shopCountryCode,
                $currencyCode,
                $totalLabel
            );
        }
        if (is_cart()) {
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
     * @param \WC_Product $product
     *
     * @return bool
     */
    protected function checkIfNeedShipping(\WC_Product $product): bool
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
     * @param string $shopCountryCode
     * @param string $currencyCode
     * @param string $totalLabel
     *
     * @return array<mixed>
     */
    protected function dataForProductPage(
        string $shopCountryCode,
        string $currencyCode,
        string $totalLabel
    ): array {

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
     * @param string $shopCountryCode
     * @param string $currencyCode
     * @param string $totalLabel
     *
     * @return array<mixed>
     */
    protected function dataForCartPage(
        string $shopCountryCode,
        string $currencyCode,
        string $totalLabel
    ): array {

        $cart = WC()->cart;
        $nonce = wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce', true, false);
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
