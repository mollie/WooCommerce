<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class BillieFieldsStrategy implements PaymentFieldsStrategyI
{
    const FIELD_COMPANY = "billing_company";

    public function execute($gateway, $dataHelper)
    {
        $showCompanyField = false;

        if (is_checkout_pay_page()) {
            $order = $this->getOrderIdOnPayForOrderPage();
            $showCompanyField = empty($order->get_billing_company());
        }

        if (is_checkout() && !is_checkout_pay_page()) {
            $showCompanyField = true;
        }

        if ($showCompanyField) {
            $this->company();
        }
    }

    protected function getOrderIdOnPayForOrderPage()
    {
        global $wp;
        $orderId = absint($wp->query_vars['order-pay']);
        return wc_get_order($orderId);
    }

    protected function company()
    {
        ?>
        <p class="form-row form-row-wide" id="billing_company_field">
            <label for="<?php echo esc_attr(self::FIELD_COMPANY); ?>" class=""><?php echo esc_html__('Company', 'mollie-payments-for-woocommerce'); ?>
                <abbr class="required" title="required">*</abbr>
            </label>
            <span class="woocommerce-input-wrapper">
        <input type="tel" class="input-text " name="<?php echo esc_attr(self::FIELD_COMPANY); ?>" id="<?php echo esc_attr(self::FIELD_COMPANY); ?>"
               placeholder="Company name"
               value="" autocomplete="organization">
        </span>
        </p>
        <?php
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}
