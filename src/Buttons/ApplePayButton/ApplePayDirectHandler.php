<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

use Mollie\WooCommerce\Notice\AdminNotice;

class ApplePayDirectHandler
{
    /**
     * @var AdminNotice
     */
    private $adminNotice;
    /**
     * @var AppleAjaxRequests
     */
    private $ajaxRequests;

    /**
     * ApplePayDirectHandler constructor.
     */
    public function __construct(AdminNotice $notice, AppleAjaxRequests $ajaxRequests)
    {
        $this->adminNotice = $notice;
        $this->ajaxRequests = $ajaxRequests;
    }

    /**
     * Initial method that checks if the device is compatible
     * if so puts the button in place
     * and adds all the necessary actions
     *
     * @param bool $buttonEnabledProduct
     * @param bool $buttonEnabledCart
     */
    public function bootstrap($buttonEnabledProduct, $buttonEnabledCart)
    {
        if (!$this->isApplePayCompatible()) {
            /* translators: Placeholder 1: Opening strong tag. Placeholder 2: Closing strong tag. Placeholder 3: Opening link tag to documentation. Placeholder 4: Closing link tag.*/
            $message = sprintf(
                esc_html__(
                    '%1$sServer not compliant with Apple requirements%2$s Check %3$sApple Server requirements page%4$s to fix it in order to make the Apple Pay button work',
                    'mollie-payments-for-woocommerce'
                ),
                '<strong>',
                '</strong>',
                '<a href="https://developer.apple.com/documentation/apple_pay_on_the_web/setting_up_your_server">',
                '</a>'
            );
            $this->adminNotice->addNotice('error', $message);
            return;
        }

        if (!$this->merchantValidated()) {
            /* translators: Placeholder 1: Opening strong tag. Placeholder 2: Closing strong tag. Placeholder 3: Opening link tag to documentation. Placeholder 4: Closing link tag.*/
            $message = sprintf(
                esc_html__(
                    '%1$sApple Pay Validation Error%2$s Check %3$sApple Server requirements page%4$s to fix it in order to make the Apple Pay button work',
                    'mollie-payments-for-woocommerce'
                ),
                '<strong>',
                '</strong>',
                '<a href="https://developer.apple.com/documentation/apple_pay_on_the_web/setting_up_your_server">',
                '</a>'
            );

            $this->adminNotice->addNotice('error', $message);
        }

        if ($buttonEnabledProduct) {
            $renderPlaceholder = apply_filters('mollie_wc_gateway_applepay_render_hook_product', 'woocommerce_after_add_to_cart_form');
            $renderPlaceholder = is_string($renderPlaceholder) ? $renderPlaceholder : 'woocommerce_after_add_to_cart_form';
            add_action(
                $renderPlaceholder,
                function () {
                    $this->applePayDirectButton();
                }
            );
        }
        if ($buttonEnabledCart) {
            $renderPlaceholder = apply_filters('mollie_wc_gateway_applepay_render_hook_cart', 'woocommerce_cart_totals_after_order_total');
            $renderPlaceholder = is_string($renderPlaceholder) ? $renderPlaceholder : 'woocommerce_cart_totals_after_order_total';
            add_action(
                $renderPlaceholder,
                function () {
                    $this->applePayDirectButton();
                }
            );
        }

        admin_url('admin-ajax.php');
        $this->ajaxRequests->bootstrapAjaxRequest();
    }

    /**
     * Checks if the server is HTTPS
     *
     * @return bool
     */
    private function isApplePayCompatible()
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
    /**
     * Checks if the merchant has been validated
     *
     * @return bool
     */
    protected function merchantValidated()
    {
        $option = get_option('mollie_wc_applepay_validated', 'yes');

        return $option === 'yes';
    }

    /**
     * ApplePay button markup
     */
    protected function applePayDirectButton()
    {
        ?>
        <div id="mollie-applepayDirect-button">
            <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');  ?>
        </div>
        <?php
    }
}

