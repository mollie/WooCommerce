<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\PayPalButton;

use Mollie\WooCommerce\Buttons\AbstractExpressButton;

class PayPalExpressButton extends AbstractExpressButton
{
    /**
     * @var PayPalAjaxRequests
     */
    protected $ajaxRequests;

    /**
     * @var DataToPayPal
     */
    private $dataPaypal;

    /**
     * @var bool
     */
    private $enabledInProduct;

    /**
     * @var bool
     */
    private $enabledInCart;

    public function __construct(
        PayPalAjaxRequests $ajaxRequests,
        DataToPayPal $dataPaypal,
        bool $enabledInProduct,
        bool $enabledInCart
    ) {
        $this->ajaxRequests = $ajaxRequests;
        $this->dataPaypal = $dataPaypal;
        $this->enabledInProduct = $enabledInProduct;
        $this->enabledInCart = $enabledInCart;
    }

    public function getId(): string
    {
        return 'paypal';
    }

    public function getButtonComponent(): string
    {
        return 'PayPalButtonComponent';
    }

    public function canShow(): bool
    {
        return $this->isEnabledInSettings();
    }

    public function getAjaxHandlers(): array
    {
        // Delegate to the existing ajax requests handler
        return $this->ajaxRequests->getHandlers();
    }

    public function getScriptData(): array
    {
        return $this->dataPaypal->paypalbuttonScriptData(false);
    }

    /**
     * Bootstrap the PayPal button - adds hooks for rendering
     * Override from AbstractExpressButton to add custom rendering logic
     */
    public function bootstrap(): void
    {
        if (!$this->canShow()) {
            return;
        }

        // Register AJAX handlers
        $this->registerAjaxHandlers();

        // Add rendering hooks for product page
        if ($this->enabledInProduct) {
            $this->registerProductPageHook();
        }

        // Add rendering hooks for cart page
        if ($this->enabledInCart) {
            $this->registerCartPageHook();
        }

        // Enqueue scripts will be called by parent class if needed
        // or handled by the blocks registration
    }

    /**
     * Register hook for product page
     */
    private function registerProductPageHook(): void
    {
        $renderPlaceholder = apply_filters(
            'mollie_wc_gateway_paypal_render_hook_product',
            'woocommerce_after_add_to_cart_form'
        );
        $renderPlaceholder = is_string($renderPlaceholder)
            ? $renderPlaceholder
            : 'woocommerce_after_add_to_cart_form';

        add_action($renderPlaceholder, function () {
            $product = wc_get_product(get_the_id());

            // Don't show for subscriptions
            if (!$product ||
                $product->is_type('subscription') ||
                $product instanceof \WC_Product_Variable_Subscription
            ) {
                return;
            }

            // Only show if product doesn't need shipping
            $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
            if (!$productNeedShipping) {
                $this->renderButton();
            }
        });
    }

    /**
     * Register hook for cart page
     */
    private function registerCartPageHook(): void
    {
        $renderPlaceholder = apply_filters(
            'mollie_wc_gateway_paypal_render_hook_cart',
            'woocommerce_cart_totals_after_order_total'
        );
        $renderPlaceholder = is_string($renderPlaceholder)
            ? $renderPlaceholder
            : 'woocommerce_cart_totals_after_order_total';

        add_action($renderPlaceholder, function () {
            $cart = WC()->cart;

            // Don't show for subscriptions
            foreach ($cart->get_cart_contents() as $product) {
                if ($product['data']->is_type('subscription') ||
                    $product['data'] instanceof \WC_Product_Subscription_Variation
                ) {
                    return;
                }
            }

            // Only show if cart doesn't need shipping
            if (!$cart->needs_shipping()) {
                $this->renderButton();
            }
        });
    }

    /**
     * Render the PayPal button HTML
     */
    private function renderButton(): void
    {
        $assetsImagesUrl = $this->dataPaypal->selectedPaypalButtonUrl();
        ?>
        <div id="mollie-PayPal-button" class="mol-PayPal">
            <?php wp_nonce_field('mollie_PayPal_button'); ?>
            <input type="image" src="<?php echo esc_url($assetsImagesUrl); ?>" alt="PayPal Button">
        </div>
        <?php
    }

    /**
     * Check if PayPal is enabled in settings
     */
    private function isEnabledInSettings(): bool
    {
        $settings = get_option('mollie_wc_gateway_paypal_settings', []);
        return isset($settings['enabled']) && $settings['enabled'] === 'yes';
    }

    /**
     * Register AJAX handlers using the ajax requests handler
     */
    protected function registerAjaxHandlers(): void
    {
        $this->ajaxRequests->bootstrapAjaxRequest();
    }
}
