<?php

class Mollie_WC_Helper_PayPalButtonHandler
{
    /**
     * @var Mollie_WC_PayPalButton_AjaxRequests
     */
    private $ajaxRequests;

    /**
     * Mollie_WC_Helper_PayPalHandler constructor.
     *
     * @param Mollie_WC_PayPalButton_AjaxRequests     $ajaxRequests
     */
    public function __construct(Mollie_WC_PayPalButton_AjaxRequests $ajaxRequests)
    {
        $this->ajaxRequests = $ajaxRequests;
    }

    /**
     * Initial method that puts the button in place
     * and adds all the necessary actions
     */
    public function bootstrap($enabledInProduct, $enabledInCart)
    {
        //y no hay shipping enseño, luego ya la cantidad en js

        if($enabledInProduct){
            add_action(
                    'woocommerce_after_single_product',
                    function () {
                        $product = wc_get_product(get_the_id());
                        if (!$product || $product->is_type('subscription')) {
                            return;
                        }
                        $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
                        if(!$productNeedShipping){
                            $this->renderPayPalButton();
                        }
                    }
            );
        }
        if($enabledInCart){
            add_action(
                    'woocommerce_cart_totals_after_order_total',
                    function () {
                        $cart = WC()->cart;
                        foreach ($cart->get_cart_contents() as $product){
                            if($product['data']->is_type('subscription')){
                                return;
                            }
                        }
                        if(!$cart->needs_shipping()){
                            $this->renderPayPalButton();
                        }
                    }
            );
        }

        admin_url('admin-ajax.php');
        $this->ajaxRequests->bootstrapAjaxRequest();
    }

    /**
     * PayPal button markup
     */
    protected function renderPayPalButton()
    {
        $whichPayPalButton = $this->whichPayPalButton();
        $assetsImagesUrl
                = Mollie_WC_Plugin::getPluginUrl($whichPayPalButton);

        ?>
        <div id="mollie-PayPal-button" class="mol-PayPal">
            <?php wp_nonce_field('mollie_PayPal_button'); ?>
            <input type="image" src="<?php echo esc_url( $assetsImagesUrl)?>" alt="PayPal Button">
        </div>
        <?php
    }

    /**
     * Build the name of the button from the settings to return the chosen one
     *
     * @retun string the path of the chosen button image
     */
    protected function whichPayPalButton()
    {
        $paypalSettings = get_option('mollie_wc_gateway_paypal_settings');
        if(!$paypalSettings){
            return "";
        }
        $colorSetting = isset( $paypalSettings['color']) ? $paypalSettings['color'] : "en-checkout-pill-golden";
        $dataArray = explode('-', $colorSetting);//[0]lang [1]folder [2]first part filename [3] second part filename
        $fixPath = 'public/images/PayPal_Buttons/';
        $buildButtonName = "{$dataArray[0]}/{$dataArray[1]}/{$dataArray[2]}-{$dataArray[3]}.png";
        $path = "{$fixPath}{$buildButtonName}";
        if(file_exists(M4W_PLUGIN_DIR . '/'. $path)){
            return "{$fixPath}{$buildButtonName}";
        }else{
            return "{$fixPath}/en/checkout/pill-golden.png";
        }

    }
}

