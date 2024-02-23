<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class In3FieldsStrategy implements PaymentFieldsStrategyI
{
    const FIELD_BIRTHDATE = "billing_birthdate";
    const FIELD_PHONE = "billing_phone";

    public function execute($gateway, $dataHelper)
    {
        $showBirthdateField = false;
        $showPhoneField = false;

        if (is_checkout_pay_page()) {
            $order = $this->getOrderIdOnPayForOrderPage();
            $showPhoneField = empty($order->get_billing_phone());
            $showBirthdateField = true;
        }

        if (is_checkout() && !is_checkout_pay_page()) {
            $checkoutFields = WC()->checkout()->get_checkout_fields();

            if (!isset($checkoutFields["billing"][self::FIELD_PHONE])) {
                $showPhoneField = true;
            }

            if (!isset($checkoutFields["billing"][self::FIELD_BIRTHDATE])) {
                $showBirthdateField = true;
            }
        }

        if ($showPhoneField) {
            $this->phoneNumber();
        }

        if ($showBirthdateField) {
            $this->dateOfBirth();
        }
    }

    protected function getOrderIdOnPayForOrderPage()
    {
        global $wp;
        $orderId = absint($wp->query_vars['order-pay']);
        return wc_get_order($orderId);
    }

    protected function dateOfBirth()
    {
        ?>
        <p class="form-row form-row-wide" id="billing_birthdate_field">
            <label for="<?= esc_attr(self::FIELD_BIRTHDATE); ?>" class=""><?= esc_html__('Birthdate', 'mollie-payments-for-woocommerce'); ?>
                <abbr class="required" title="required">*</abbr>
            </label>
            <span class="woocommerce-input-wrapper">
                <input type="date" class="input-text " name="<?= esc_attr(self::FIELD_BIRTHDATE); ?>"
                       id="<?= esc_attr(self::FIELD_BIRTHDATE); ?>" value=""
                       autocomplete="birthdate"></span>
        </p>
        <?php
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
