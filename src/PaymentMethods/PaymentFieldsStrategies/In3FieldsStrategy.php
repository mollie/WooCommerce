<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\Shared\FieldConstants;
class In3FieldsStrategy extends \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
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
            $html .= $this->dateOfBirth($birthValue, FieldConstants::IN3_BIRTHDATE);
        }
        return $html;
    }
    protected function phoneNumber($phoneValue)
    {
        $phoneValue = $phoneValue ?: '';
        $html = '<p class="form-row form-row-wide" id="billing_phone_field">';
        $html .= '<label for="' . esc_attr(FieldConstants::IN3_PHONE) . '" class="">' . esc_html__('Phone', 'mollie-payments-for-woocommerce') . '</label>';
        $html .= '<span class="woocommerce-input-wrapper">';
        $html .= '<input type="tel" class="input-text " name="' . esc_attr(FieldConstants::IN3_PHONE) . '" id="' . esc_attr(FieldConstants::IN3_PHONE) . '" placeholder="+316xxxxxxxx" value="' . esc_attr($phoneValue) . '" autocomplete="phone">';
        $html .= '</span></p>';
        return $html;
    }
    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
