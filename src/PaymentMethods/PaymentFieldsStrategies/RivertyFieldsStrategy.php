<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class RivertyFieldsStrategy implements PaymentFieldsStrategyI
{
    use PaymentFieldsStrategiesTrait;

    const FIELD_BIRTHDATE = "billing_birthdate";
    const FIELD_PHONE = "billing_phone_riverty";

    public function execute($gateway, $dataHelper)
    {
        $showBirthdateField = false;
        $showPhoneField = false;
        $isPhoneRequired = get_option('mollie_wc_is_phone_required_flag');
        $phoneValue = false;
        $birthValue = false;

        if (is_checkout_pay_page()) {
            $showBirthdateField = true;
            $showPhoneField = true;
            $order = $this->getOrderIdOnPayForOrderPage();
            $phoneValue = $order->get_billing_phone();
            $birthValue = $order->get_meta('billing_birthdate');
        }

        if (is_checkout() && !is_checkout_pay_page() && !$isPhoneRequired) {
            $showPhoneField = true;
        }
        if (is_checkout() && !is_checkout_pay_page()) {
            $showBirthdateField = true;
        }

        if ($showPhoneField) {
            $this->phoneNumber($phoneValue);
        }

        if ($showBirthdateField) {
            $this->dateOfBirth($birthValue);
        }
    }

    protected function phoneNumber($phoneValue)
    {
        $phoneValue = $phoneValue ?: '';
        $country = WC()->customer->get_billing_country();
        $countryCodes = [
            'BE' => '+32xxxxxxxxx',
            'NL' => '+316xxxxxxxx',
            'DE' => '+49xxxxxxxxx',
            'AT' => '+43xxxxxxxxx',
        ];
        $placeholder = in_array($country, array_keys($countryCodes)) ? $countryCodes[$country] : $countryCodes['NL'];
        ?>
        <p class="form-row form-row-wide" id="billing_phone_field">
            <label for="<?php echo esc_attr(self::FIELD_PHONE); ?>" class=""><?php echo esc_html__('Phone', 'mollie-payments-for-woocommerce'); ?>
            </label>
            <span class="woocommerce-input-wrapper">
        <input type="tel" class="input-text " name="<?php echo esc_attr(self::FIELD_PHONE); ?>" id="<?php echo esc_attr(self::FIELD_PHONE); ?>"
               placeholder="<?php echo esc_attr($placeholder); ?>"
               value="<?php echo esc_attr($phoneValue); ?>" autocomplete="phone">
        </span>
        </p>
        <?php
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
