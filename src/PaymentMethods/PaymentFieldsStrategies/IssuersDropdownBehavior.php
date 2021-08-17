<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

trait IssuersDropdownBehavior
{
    public function getIssuers($gateway, $dataHelper)
    {
        $testMode = $gateway->settingsHelper->isTestModeEnabled();
        $apiKey = $gateway->settingsHelper->getApiKey($testMode);

        return $dataHelper->getMethodIssuers(
            $apiKey,
            $testMode,
            $gateway->paymentMethod->getProperty('id')
        );
    }
    public function renderIssuers($gateway, $issuers, $selectedIssuer)
    {
        $description = $gateway->paymentMethod->getProperty(
            'issuers_empty_option'
        ) ?: $gateway->paymentMethod->getProperty('defaultDescription');

        $html = '<select name="' . $gateway->pluginId . '_issuer_' . $gateway->id . '">';
        $html .= '<option value="">' . esc_html(__($description, 'mollie-payments-for-woocommerce')) . '</option>';
        foreach ($issuers as $issuer) {
            $html .= '<option value="' . esc_attr($issuer->id) . '"'
                . ($selectedIssuer == $issuer->id ? ' selected=""' : '') . '>'
                . esc_html($issuer->name) . '</option>';
        }
        $html .= '</select>';
        echo wpautop(wptexturize($html));
    }
}
