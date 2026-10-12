<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\Shared\FieldConstants;
class RivertyFieldsStrategy extends \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    use \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsStrategiesTrait;
    public function renderFields(): string
    {
        $showBirthdateField = \false;
        $showPhoneField = \false;
        $isPhoneRequired = get_option('mollie_wc_is_phone_required_flag');
        $phoneValue = \false;
        $birthValue = \false;
        $html = $this->gatewayDescription;
        if (is_checkout_pay_page()) {
            $showBirthdateField = \true;
            $showPhoneField = \true;
            $order = $this->getOrderIdOnPayForOrderPage();
            $phoneValue = $order->get_billing_phone();
            $birthValue = $order->get_meta('billing_birthdate', \true);
        }
        if (is_checkout() && !is_checkout_pay_page() && !$isPhoneRequired) {
            $showPhoneField = \true;
        }
        if (is_checkout() && !is_checkout_pay_page()) {
            $showBirthdateField = \true;
        }
        if ($showPhoneField) {
            $html .= $this->phoneNumber($phoneValue);
        }
        if ($showBirthdateField) {
            $html .= $this->dateOfBirth($birthValue, FieldConstants::RIVERTY_BIRTHDATE);
        }
        return $html;
    }
    protected function phoneNumber($phoneValue)
    {
        $phoneValue = $phoneValue ?: '';
        $country = WC()->customer->get_billing_country();
        $countryCodes = ['BE' => '+32xxxxxxxxx', 'NL' => '+316xxxxxxxx', 'DE' => '+49xxxxxxxxx', 'AT' => '+43xxxxxxxxx'];
        $placeholder = in_array($country, array_keys($countryCodes)) ? $countryCodes[$country] : $countryCodes['NL'];
        $html = '<p class="form-row form-row-wide" id="billing_phone_field">';
        $html .= '<label for="' . esc_attr(FieldConstants::RIVERTY_PHONE) . '" class="">' . esc_html__('Phone', 'mollie-payments-for-woocommerce') . '</label>';
        $html .= '<span class="woocommerce-input-wrapper">';
        $html .= '<input type="tel" class="input-text " name="' . esc_attr(FieldConstants::RIVERTY_PHONE) . '" id="' . esc_attr(FieldConstants::RIVERTY_PHONE) . '" placeholder="' . esc_attr($placeholder) . '" value="' . esc_attr($phoneValue) . '" autocomplete="phone">';
        $html .= '</span></p>';
        return $html;
    }
    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
