<?php

class Mollie_WC_Helper_ApplePayDirectHandler
{
    /**
     * @var Mollie_WC_Notice_AdminNotice
     */
    private $adminNotice;
    /**
     * @var Mollie_WC_ApplePayButton_AjaxRequests
     */
    private $ajaxRequests;

    /**
     * Mollie_WC_Helper_ApplePayDirectHandler constructor.
     *
     * @param Mollie_WC_Notice_AdminNotice              $notice
     * @param Mollie_WC_ApplePayButton_AjaxRequests     $ajaxRequests
     */
    public function __construct(Mollie_WC_Notice_AdminNotice $notice, Mollie_WC_ApplePayButton_AjaxRequests $ajaxRequests)
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


        if($buttonEnabledProduct){
            add_action(
                    'woocommerce_after_add_to_cart_form',
                    function () {
                        $this->applePayDirectButton();
                    }
            );
        }
        if($buttonEnabledCart){
            add_action(
                    'woocommerce_cart_totals_after_order_total',
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
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
    }
    /**
     * Checks if the merchant has been validated
     *
     * @return bool
     */
    protected function merchantValidated()
    {
        $option = get_option('mollie_wc_applepay_validated', 'yes');

        return $option == 'yes';
    }

    /**
     * ApplePay button markup
     */
    protected function applePayDirectButton()
    {
        ?>
        <div id="mollie-applepayDirect-button">
            <?php wp_nonce_field('mollie_applepay_button'); ?>
        </div>
        <?php
    }
}

