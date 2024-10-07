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
        ?>
        <p class="form-row form-row-wide" id="billing_birthdate_field">
            <label for="<?php echo esc_attr(self::FIELD_BIRTHDATE); ?>" class=""><?php echo esc_html__('Birthdate', 'mollie-payments-for-woocommerce'); ?>
            </label>
            <span class="woocommerce-input-wrapper">
                <input type="date" class="input-text " name="<?php echo esc_attr(self::FIELD_BIRTHDATE); ?>"
                       id="<?php echo esc_attr(self::FIELD_BIRTHDATE); ?>" value="<?php echo esc_attr($birthValue); ?>"
                       autocomplete="birthdate"></span>
        </p>
        <?php
    }
}
