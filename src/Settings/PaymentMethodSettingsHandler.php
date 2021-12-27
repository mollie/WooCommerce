<?php

namespace Mollie\WooCommerce\Settings;


use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodSettingsHandlerI;

class PaymentMethodSettingsHandler implements PaymentMethodSettingsHandlerI
{

    public function getSettings(PaymentMethodI $paymentMethod)
    {
        $paymentMethodId = $paymentMethod->getProperty('id');
        $optionName = 'mollie_wc_gateway_' . $paymentMethodId . '_settings';
        return get_option($optionName, false);
    }
}
