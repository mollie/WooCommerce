<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\Settings\Settings;
trait ConnectionStatusTrait
{
    protected function connectionStatusField(Settings $settings, bool $connectionStatus): array
    {
        return ['id' => $settings->getSettingId('connection_status'), 'title' => __('Mollie Connection Status', 'mollie-payments-for-woocommerce'), 'value' => $this->connectionStatus($settings, $connectionStatus), 'type' => 'mollie_custom_input'];
    }
    protected function connectionStatus(Settings $settings, bool $connectionStatus): ?string
    {
        $testMode = $settings->isTestModeEnabled();
        if (!$connectionStatus) {
            return __('Failed to connect to Mollie API - check your API keys &#x2716;', 'mollie-payments-for-woocommerce');
        }
        if ($testMode) {
            return __('Successfully connected with <strong>Test API</strong> &#x2713;', 'mollie-payments-for-woocommerce');
        }
        return __('Successfully connected with <strong>Live API</strong> &#x2713;', 'mollie-payments-for-woocommerce');
    }
}
