<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

use WC_Admin_Settings;
use WC_Settings_Page;

class Components extends WC_Settings_Page
{
    public const FILTER_COMPONENTS_SETTINGS = 'components_settings';
    /**
     * @var string
     */
    protected $pluginPath;

    public function __construct(string $pluginPath)
    {
        $this->id = 'mollie_components';
        $this->label = __('Mollie Components', 'mollie-payments-for-woocommerce');
        $this->pluginPath = $pluginPath;

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
        return $this->pluginPath . '/inc/settings/mollie_components.php';
    }
}
