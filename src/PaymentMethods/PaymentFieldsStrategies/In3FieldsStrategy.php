<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class In3FieldsStrategy implements PaymentFieldsStrategyI
{
    use PaymentFieldsStrategiesTrait;

    const FIELD_BIRTHDATE = "billing_birthdate_in3";
    const FIELD_PHONE = "billing_phone_in3";

    public function execute($gateway, $dataHelper): string
    {
        $showBirthdateField = false;
        $showPhoneField = false;
        $isPhoneRequired = get_option('mollie_wc_is_phone_required_flag');
        $phoneValue = false;
        $birthValue = false;
        $html = '';
        if (is_checkout_pay_page()) {
            $showBirthdateField = true;
            $showPhoneField = true;
            $order = $this->getOrderIdOnPayForOrderPage();
            $phoneValue = $order->get_billing_phone();
            $birthValue = $order->get_meta(self::FIELD_BIRTHDATE);
        }

        if (is_checkout() && !is_checkout_pay_page() && !$isPhoneRequired) {
            $showPhoneField = true;
        }
        if (is_checkout() && !is_checkout_pay_page()) {
            $showBirthdateField = true;
        }

        if ($showPhoneField) {
            $html .= $this->phoneNumber($phoneValue);
        }

        if ($showBirthdateField) {
            $html .= $this->dateOfBirth($birthValue);
        }
        return $html;
    }

    protected function phoneNumber($phoneValue)
    {
        $phoneValue = $phoneValue ?: '';
        $html = '<p class="form-row form-row-wide" id="billing_phone_field">';
        $html .= '<label for="' . esc_attr(self::FIELD_PHONE) . '" class="">' . esc_html__(
                        'Phone',
                        'mollie-payments-for-woocommerce'
                ) . '</label>';
        $html .= '<span class="woocommerce-input-wrapper">';
        $html .= '<input type="tel" class="input-text " name="' . esc_attr(self::FIELD_PHONE) . '" id="' . esc_attr(
                        self::FIELD_PHONE
                ) . '" placeholder="+316xxxxxxxx" value="' . esc_attr($phoneValue) . '" autocomplete="phone">';
        $html .= '</span></p>';
        return $html;
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
