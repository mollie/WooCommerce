<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\PayPalButton;

class PayPalButtonHandler
{
    /**
     * @var PayPalAjaxRequests
     */
    private $ajaxRequests;
    /**
     * @var DataToPayPal
     */
    protected $dataPaypal;
    /**
     * PayPalHandler constructor.
     */
    public function __construct(\Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests $ajaxRequests, \Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal $dataPaypal)
    {
        $this->ajaxRequests = $ajaxRequests;
        $this->dataPaypal = $dataPaypal;
    }
    /**
     * Initial method that puts the button in place
     * and adds all the necessary actions
     */
    public function bootstrap($enabledInProduct, $enabledInCart)
    {
        if ($enabledInProduct) {
            $renderPlaceholder = apply_filters('mollie_wc_gateway_paypal_render_hook_product', 'woocommerce_after_add_to_cart_form');
            $renderPlaceholder = is_string($renderPlaceholder) ? $renderPlaceholder : 'woocommerce_after_add_to_cart_form';
            add_action($renderPlaceholder, function () {
                $product = wc_get_product(get_the_id());
                if (!$product || $product->is_type('subscription') || $product instanceof \WC_Product_Variable_Subscription) {
                    return;
                }
                $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
                if (!$productNeedShipping) {
                    $this->renderPayPalButton();
                }
            });
        }
        if ($enabledInCart) {
            $renderPlaceholder = apply_filters('mollie_wc_gateway_paypal_render_hook_cart', 'woocommerce_cart_totals_after_order_total');
            $renderPlaceholder = is_string($renderPlaceholder) ? $renderPlaceholder : 'woocommerce_cart_totals_after_order_total';
            add_action($renderPlaceholder, function () {
                $cart = WC()->cart;
                foreach ($cart->get_cart_contents() as $product) {
                    if ($product['data']->is_type('subscription') || $product['data'] instanceof \WC_Product_Subscription_Variation) {
                        return;
                    }
                }
                if (!$cart->needs_shipping()) {
                    $this->renderPayPalButton();
                }
            });
        }
        admin_url('admin-ajax.php');
        $this->ajaxRequests->bootstrapAjaxRequest();
    }
    /**
     * PayPal button markup
     */
    protected function renderPayPalButton()
    {
        $assetsImagesUrl = $this->dataPaypal->selectedPaypalButtonUrl();
        ?>
        <div id="mollie-PayPal-button" class="mol-PayPal">
            <?php 
        wp_nonce_field('mollie_PayPal_button');
        ?>
            <input type="image" src="<?php 
        echo esc_url($assetsImagesUrl);
        ?>" alt="PayPal Button">
        </div>
        <?php 
    }
}
