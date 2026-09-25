<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\Shared\FieldConstants;
class VippsFieldsStrategy extends \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    use \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsStrategiesTrait;
    public function renderFields(): string
    {
        $showPhoneField = \false;
        $isPhoneRequired = get_option('mollie_wc_is_phone_required_flag');
        $phoneValue = \false;
        $html = $this->gatewayDescription;
        if (is_checkout_pay_page()) {
            $showPhoneField = \true;
            $order = $this->getOrderIdOnPayForOrderPage();
            $phoneValue = $order->get_billing_phone();
        }
        if (is_checkout() && !is_checkout_pay_page() && !$isPhoneRequired) {
            $showPhoneField = \true;
        }
        if ($showPhoneField) {
            $html .= $this->phoneNumber($phoneValue);
        }
        return $html;
    }
    protected function phoneNumber($phoneValue)
    {
        $phoneValue = $phoneValue ?: '';
        $country = WC()->customer->get_billing_country();
        $countryCodes = ['NO' => '+47xxxxxxxxx'];
        $placeholder = in_array($country, array_keys($countryCodes)) ? $countryCodes[$country] : $countryCodes['NO'];
        $html = '<p class="form-row form-row-wide" id="billing_phone_field">';
        $html .= '<label for="' . esc_attr(FieldConstants::VIPPS_PHONE) . '" class="">' . esc_html__('Phone', 'mollie-payments-for-woocommerce') . '</label>';
        $html .= '<span class="woocommerce-input-wrapper">';
        $html .= '<input type="tel" class="input-text " name="' . esc_attr(FieldConstants::VIPPS_PHONE) . '" id="' . esc_attr(FieldConstants::VIPPS_PHONE) . '" placeholder="' . esc_attr($placeholder) . '" value="' . esc_attr($phoneValue) . '" autocomplete="phone">';
        $html .= '</span></p>';
        return $html;
    }
    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
