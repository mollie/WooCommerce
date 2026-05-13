<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Migration;

class PaymentMethodSettingsMigrator implements \Mollie\WooCommerce\Migration\MigratorInterface
{
    public function migrate(): void
    {
        global $wpdb;
        $optionNames = $wpdb->get_col("SELECT option_name FROM {$wpdb->options}\n             WHERE option_name LIKE 'mollie_wc_gateway_%_settings'");
        foreach ($optionNames as $optionName) {
            $settings = get_option($optionName, \false);
            if (!is_array($settings)) {
                continue;
            }
            $settings = $this->migrateTitle($settings);
            $settings = $this->migrateLogo($settings);
            update_option($optionName, $settings);
        }
    }
    private function migrateTitle(array $settings): array
    {
        $useApiTitle = $settings['use_api_title'] ?? 'yes';
        if ($useApiTitle !== 'no' || empty($settings['title'])) {
            unset($settings['title']);
        }
        unset($settings['use_api_title']);
        return $settings;
    }
    private function migrateLogo(array $settings): array
    {
        $enableCustomLogo = $settings['enable_custom_logo'] ?? 'no';
        $hasUrl = !empty($settings['iconFileUrl']) && is_string($settings['iconFileUrl']);
        if ($enableCustomLogo !== 'yes' || !$hasUrl) {
            unset($settings['iconFileUrl'], $settings['iconFilePath']);
        }
        unset($settings['enable_custom_logo']);
        return $settings;
    }
}
