<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Plugin;
use WC_Admin_Settings;
use WC_Settings_Page;

class Components extends WC_Settings_Page
{
    const FILTER_COMPONENTS_SETTINGS = 'components_settings';

    public function __construct()
    {
        $this->id = 'mollie_components';
        $this->label = __('Mollie Components', 'mollie-payments-for-woocommerce');

        parent::__construct();
    }

    public function output()
    {
        $settings = $this->get_settings();
        WC_Admin_Settings::output_fields($settings);
    }

    public function get_settings()
    {
        $componentsSettings = $this->componentsSettings();

        /**
         * Filter Component Settings
         *
         * @param array $componentSettings Default components settings for the Credit Card Gateway
         */
        $componentsSettings = apply_filters(self::FILTER_COMPONENTS_SETTINGS, $componentsSettings);

        return $componentsSettings;
    }

    protected function componentsSettings()
    {
        $componentSettingsFilePath = $this->componentsFilePath();

        if (!file_exists($componentSettingsFilePath)) {
            return [];
        }

        $components = include $componentSettingsFilePath;

        if (!is_array($components)) {
            $components = [];
        }

        return $components;
    }

    protected function componentsFilePath()
    {
        return Plugin::getPluginPath(
            '/inc/settings/mollie_components.php'
        );
    }
}
