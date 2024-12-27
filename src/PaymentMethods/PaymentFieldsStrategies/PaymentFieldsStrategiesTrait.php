<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

trait PaymentFieldsStrategiesTrait
{
    protected function getOrderIdOnPayForOrderPage()
    {
        global $wp;
        $orderId = absint($wp->query_vars['order-pay']);
        return wc_get_order($orderId);
    }

    protected function dateOfBirth($birthValue)
    {
        $birthValue = $birthValue ?: '';
        $html = '<p class="form-row form-row-wide" id="billing_birthdate_field">';
        $html .= '<label for="' . esc_attr(self::FIELD_BIRTHDATE) . '" class="">' . esc_html__(
            'Birthdate',
            'mollie-payments-for-woocommerce'
        ) . '</label>';
        $html .= '<span class="woocommerce-input-wrapper">';
        $html .= '<input type="date" class="input-text " name="' . esc_attr(
            self::FIELD_BIRTHDATE
        ) . '" id="' . esc_attr(self::FIELD_BIRTHDATE) . '" value="' . esc_attr(
            $birthValue
        ) . '" autocomplete="birthdate">';
        $html .= '</span></p>';
        return $html;
    }
}
