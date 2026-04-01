<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

trait PaymentFieldsStrategiesTrait
{
    /**
     * @return \WC_Order|false
     */
    protected function getOrderIdOnPayForOrderPage()
    {
        global $wp;
        $orderId = absint($wp->query_vars['order-pay']);
        return wc_get_order($orderId);
    }
    /**
     * @param string|false $birthValue
     * @param string       $birthdateField
     * @return string
     */
    protected function dateOfBirth($birthValue, $birthdateField): string
    {
        $birthValue = $birthValue ?: '';
        $html = '<p class="form-row form-row-wide" id="billing_birthdate_field">';
        $html .= '<label for="' . esc_attr($birthdateField) . '" class="">' . esc_html__('Birthdate', 'mollie-payments-for-woocommerce') . '</label>';
        $html .= '<span class="woocommerce-input-wrapper">';
        $html .= '<input type="date" class="input-text " name="' . esc_attr($birthdateField) . '" id="' . esc_attr($birthdateField) . '" value="' . esc_attr($birthValue) . '" autocomplete="birthdate">';
        $html .= '</span></p>';
        return $html;
    }
}
