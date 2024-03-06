<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class BancomatpayFieldsStrategy implements PaymentFieldsStrategyI
{
    const FIELD_PHONE = "billing_phone_bancomatpay";

    public function execute($gateway, $dataHelper)
    {
        $showPhoneField = false;

        if (is_checkout_pay_page()) {
            $order = $this->getOrderIdOnPayForOrderPage();
            $showPhoneField = empty($order->get_billing_phone());
        }

        if (is_checkout() && !is_checkout_pay_page()) {
                $showPhoneField = true;
        }

        if ($showPhoneField) {
            $this->phoneNumber();
        }
    }

    protected function getOrderIdOnPayForOrderPage()
    {
        global $wp;
        $orderId = absint($wp->query_vars['order-pay']);
        return wc_get_order($orderId);
    }

    protected function phoneNumber()
    {
        ?>
        <p class="form-row form-row-wide" id="billing_phone_field">
            <label for="<?= esc_attr(self::FIELD_PHONE); ?>" class=""><?= esc_html__('Phone', 'mollie-payments-for-woocommerce'); ?>
                <abbr class="required" title="required">*</abbr>
            </label>
            <span class="woocommerce-input-wrapper">
        <input type="tel" class="input-text " name="<?= esc_attr(self::FIELD_PHONE); ?>" id="<?= esc_attr(self::FIELD_PHONE); ?>"
               placeholder="+00000000000"
               value="" autocomplete="phone">
        </span>
        </p>
        <?php
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
