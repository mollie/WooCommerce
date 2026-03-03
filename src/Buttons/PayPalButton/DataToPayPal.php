<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\PayPalButton;

class DataToPayPal
{
    /**
     * @var string
     */
    protected $pluginUrl;
    /**
     * DataToPayPal constructor.
     */
    public function __construct(string $pluginUrl)
    {
        $this->pluginUrl = $pluginUrl;
    }
    /**
     * Sets the appropriate data to send to PayPal script
     * Data differs between product page and cart page
     */
    public function paypalbuttonScriptData($isBlock = \false): array
    {
        $paypalSettings = get_option('mollie_wc_gateway_paypal_settings', \false);
        $minAmount = 0;
        if ($paypalSettings) {
            $minAmount = isset($paypalSettings['mollie_paypal_button_minimum_amount']) && $paypalSettings['mollie_paypal_button_minimum_amount'] > 0 ? $paypalSettings['mollie_paypal_button_minimum_amount'] : 0;
        }
        if (is_product()) {
            return $this->dataForProductPage($minAmount);
        }
        if (is_cart()) {
            return $this->dataForCartPage($minAmount);
        }
        if ($isBlock) {
            return $this->dataForBlockCartPage($minAmount);
        }
        return [];
    }
    /**
     * Url for the button image selected in settings
     */
    public function selectedPaypalButtonUrl(): string
    {
        $whichPayPalButton = $this->whichPayPalButton();
        return $this->pluginUrl . $whichPayPalButton;
    }
    /**
     * Build the name of the button from the settings to return the chosen one
     *
     * @return string the path of the chosen button image
     */
    protected function whichPayPalButton(): string
    {
        $paypalSettings = get_option('mollie_wc_gateway_paypal_settings');
        if (!$paypalSettings) {
            return "";
        }
        $colorSetting = isset($paypalSettings['color']) ? $paypalSettings['color'] : "en-checkout-pill-golden";
        $dataArray = explode('-', $colorSetting);
        //[0]lang [1]folder [2]first part filename [3] second part filename
        $fixPath = 'public/images/PayPal_Buttons/';
        $buildButtonName = sprintf('%s/%s/%s-%s.png', $dataArray[0], $dataArray[1], $dataArray[2], $dataArray[3]);
        $path = sprintf('%s%s', $fixPath, $buildButtonName);
        if (file_exists(\M4W_PLUGIN_DIR . '/' . $path)) {
            return sprintf('%s%s', $fixPath, $buildButtonName);
        } else {
            return sprintf('%s/en/checkout/pill-golden.png', $fixPath);
        }
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
            return \false;
        }
        $isVariation = \false;
        if ($product->get_type() === 'variable') {
            $isVariation = \true;
        }
        $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
        $productId = get_the_id();
        $productPrice = $product->get_price();
        $productStock = $product->get_stock_status();
        return ['product' => ['needShipping' => $productNeedShipping, 'id' => $productId, 'price' => $productPrice, 'isVariation' => $isVariation, 'minFee' => $minAmount, 'stock' => $productStock], 'ajaxUrl' => admin_url('admin-ajax.php')];
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
        return ['product' => ['needShipping' => $cart->needs_shipping(), 'minFee' => $minAmount], 'ajaxUrl' => admin_url('admin-ajax.php')];
    }
    /**
     *
     * @param $minAmount
     *
     * @return array
     */
    protected function dataForBlockCartPage($minAmount): array
    {
        $nonce = wp_nonce_field('mollie_PayPal_button');
        $buttonMarkup = '<div id="mollie-PayPal-button" class="mol-PayPal">' . $nonce . '<input type="image" src="' . esc_url($this->selectedPaypalButtonUrl()) . '" alt="PayPal Button"></div>';
        return ['minFee' => $minAmount, 'ajaxUrl' => admin_url('admin-ajax.php'), 'buttonMarkup' => $buttonMarkup];
    }
}
