<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\Shared\FieldConstants;
class BancomatpayFieldsStrategy extends \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    public function renderFields(): string
    {
        $showPhoneField = \false;
        $isPhoneRequired = get_option('mollie_wc_is_phone_required_flag');
        $phoneValue = \false;
        if (is_checkout_pay_page()) {
            $order = $this->getOrderIdOnPayForOrderPage();
            $phoneValue = $order->get_billing_phone();
            $showPhoneField = \true;
        }
        if (is_checkout() && !is_checkout_pay_page() && !$isPhoneRequired) {
            $showPhoneField = \true;
        }
        if ($showPhoneField) {
            return $this->gatewayDescription . $this->phoneNumber($phoneValue);
        }
        return $this->gatewayDescription;
    }
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
     * @param string|false $phoneValue
     * @return string
     */
    protected function phoneNumber($phoneValue): string
    {
        $phoneValue = $phoneValue ?: '';
        return '
            <p class="form-row form-row-wide" id="billing_phone_field">
                <label for="' . esc_attr(FieldConstants::BANCOMATPAY_PHONE) . '" class="">' . esc_html__('Phone', 'mollie-payments-for-woocommerce') . '
                    <abbr class="required" title="required">*</abbr>
                </label>
                <span class="woocommerce-input-wrapper">
                    <input type="tel" class="input-text" name="' . esc_attr(FieldConstants::BANCOMATPAY_PHONE) . '" id="' . esc_attr(FieldConstants::BANCOMATPAY_PHONE) . '"
                           placeholder="+39xxxxxxxxx"
                           value="' . esc_attr($phoneValue) . '" autocomplete="phone">
                </span>
            </p>';
    }
    /**
     * @param mixed $gateway
     * @param mixed $dataHelper
     * @return mixed
     */
    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
