<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class BancomatpayFieldsStrategy implements PaymentFieldsStrategyI
{
    const FIELD_PHONE = "billing_phone_bancomatpay";

    public function execute($gateway, $dataHelper)
    {
        $showPhoneField = false;
        $isPhoneRequired = get_option('mollie_wc_is_phone_required_flag');
        $phoneValue = false;

        if (is_checkout_pay_page()) {
            $order = $this->getOrderIdOnPayForOrderPage();
            $phoneValue = $order->get_billing_phone();
            $showPhoneField = true;
        }

        if (is_checkout() && !is_checkout_pay_page() && !$isPhoneRequired) {
            $showPhoneField = true;
        }

        if ($showPhoneField) {
            $this->phoneNumber($phoneValue);
        }
    }

    protected function getOrderIdOnPayForOrderPage()
    {
        global $wp;
        $orderId = absint($wp->query_vars['order-pay']);
        return wc_get_order($orderId);
    }

    protected function phoneNumber($phoneValue)
    {
        $phoneValue = $phoneValue ?: '';
        ?>
        <p class="form-row form-row-wide" id="billing_phone_field">
            <label for="<?= esc_attr(self::FIELD_PHONE); ?>" class=""><?= esc_html__('Phone', 'mollie-payments-for-woocommerce'); ?>
                <abbr class="required" title="required">*</abbr>
            </label>
            <span class="woocommerce-input-wrapper">
        <input type="tel" class="input-text " name="<?= esc_attr(self::FIELD_PHONE); ?>" id="<?= esc_attr(self::FIELD_PHONE); ?>"
               placeholder="+39xxxxxxxxx"
               value="<?= esc_attr($phoneValue); ?>" autocomplete="phone">
        </span>
        </p>
        <?php
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
