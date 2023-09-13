<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Page\MollieSettingsPage;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\Status;
use Mollie\WooCommerce\Uninstall\CleanDb;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

class SettingsModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;

    protected $settingsHelper;
    /**
     * @var mixed
     */
    protected $plugin_basename;
    /**
     * @var mixed
     */
    protected $isTestModeEnabled;
    /**
     * @var mixed
     */
    protected $dataHelper;
    /**
     * @var boolean
     */
    protected $isTestNoticePrinted;

    public function services(): array
    {
        return [
            'settings.settings_helper' => static function (ContainerInterface $container): Settings {
                $pluginId = $container->get('shared.plugin_id');
                $pluginUrl = $container->get('shared.plugin_url');
                $statusHelper = $container->get('shared.status_helper');
                assert($statusHelper instanceof Status);
                $pluginVersion = $container->get('shared.plugin_version');
                $apiHelper =  $container->get('SDK.api_helper');
                assert($apiHelper instanceof Api);
                $cleanDb = $container->get(CleanDb::class);
                assert($cleanDb instanceof CleanDb);
                return new Settings(
                    $pluginId,
                    $statusHelper,
                    $pluginVersion,
                    $pluginUrl,
                    $apiHelper,
                    $cleanDb
                );
            },
            'settings.data_helper' => static function (ContainerInterface $container): Data {
                $apiHelper = $container->get('SDK.api_helper');
                assert($apiHelper instanceof Api);
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                $pluginId = $container->get('shared.plugin_id');
                $pluginPath = $container->get('shared.plugin_path');
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                return new Data($apiHelper, $logger, $pluginId, $settings, $pluginPath);
            },
            'settings.IsTestModeEnabled' => static function (ContainerInterface $container): bool {
                $settingsHelper = $container->get('settings.settings_helper');
                assert($settingsHelper instanceof Settings);
                return $settingsHelper->isTestModeEnabled();
            },
            'settings.IsDebugEnabled' => static function (): bool {
                $debugEnabled = get_option('mollie-payments-for-woocommerce_debug', true);
                return $debugEnabled === 'yes';
            },
            'settings.advanced_default_options' => static function (ContainerInterface $container) {
                $pluginPath = $container->get('shared.plugin_path');
                $advancedSettingsFilePath = $pluginPath . 'inc/settings/mollie_advanced_settings.php';
                if (!file_exists($advancedSettingsFilePath)) {
                    return [];
                }
                return include $advancedSettingsFilePath;
            },
            'settings.components_default_options' => static function (ContainerInterface $container) {
                $pluginPath = $container->get('shared.plugin_path');
                $componentsSettingsFilePath = $pluginPath . 'inc/settings/mollie_components.php';
                if (!file_exists($componentsSettingsFilePath)) {
                    return [];
                }
                return include $componentsSettingsFilePath;
            },
            'settings.option_name' => static function () {
                return 'mollie-payments-for-woocommerce_';
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $this->plugin_basename = $container->get('shared.plugin_file');
        $this->settingsHelper = $container->get('settings.settings_helper');
        assert($this->settingsHelper instanceof Settings);
        $this->isTestModeEnabled = $container->get('settings.IsTestModeEnabled');
        $this->dataHelper = $container->get('settings.data_helper');
        assert($this->dataHelper instanceof Data);
        $pluginPath = $container->get('shared.plugin_path');

        $paymentMethods = $container->get('gateway.paymentMethods');
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'addPluginActionLinks']);
        //init settings with advanced and components defaults if not exists
        $optionName = $container->get('settings.option_name');
        $defaultAdvancedOptions = $container->get('settings.advanced_default_options');
        $defaultComponentsOptions = $container->get('settings.components_default_options');
        add_action(
            'init',
            function () use ($optionName, $defaultAdvancedOptions, $defaultComponentsOptions) {
                $testedOption = 'order_status_cancelled_payments';
                $this->maybeSaveDefaultSettings($optionName, $testedOption, $defaultAdvancedOptions);
                $testedOption = 'backgroundColor';
                $this->maybeSaveDefaultSettings('mollie_components_', $testedOption, $defaultComponentsOptions);
            },
            10,
            2
        );
        $this->isTestNoticePrinted = false;
        add_action('woocommerce_settings_saved', function () {
            $isNoticePrinted = $this->maybeTestModeNotice();
            if ($isNoticePrinted) {
                $this->isTestNoticePrinted = true;
            }
        });
        add_action('admin_init', function () {
            if ($this->isTestNoticePrinted) {
                return;
            }
            $this->maybeTestModeNotice();
        });

        $gateways = $container->get('gateway.instances');
        $isSDDGatewayEnabled = $container->get('gateway.isSDDGatewayEnabled');
        $this->initMollieSettingsPage($isSDDGatewayEnabled, $gateways, $pluginPath, $paymentMethods);
        add_action(
            'woocommerce_admin_settings_sanitize_option',
            [$this->settingsHelper, 'updateMerchantIdOnApiKeyChanges'],
            10,
            2
        );
        add_action(
            'update_option_mollie-payments-for-woocommerce_live_api_key',
            [$this->settingsHelper, 'updateMerchantIdAfterApiKeyChanges'],
            10,
            3
        );
        add_action(
            'update_option_mollie-payments-for-woocommerce_test_api_key',
            [$this->settingsHelper, 'updateMerchantIdAfterApiKeyChanges'],
            10,
            3
        );

        return true;
    }

    /**
     * Add plugin action links
     * @param array $links
     * @return array
     */
    public function addPluginActionLinks(array $links): array
    {
        $action_links = [
            // Add link to global Mollie settings
            '<a href="' . $this->settingsHelper->getGlobalSettingsUrl()
            . '">' . __('Mollie settings', 'mollie-payments-for-woocommerce')
            . '</a>',
        ];

        // Add link to WooCommerce logs
        $action_links[] = '<a href="' . $this->settingsHelper->getLogsUrl()
            . '">' . __('Logs', 'mollie-payments-for-woocommerce') . '</a>';
        return array_merge($action_links, $links);
    }

    public function maybeTestModeNotice(): bool
    {
        $testModeEnabled = get_option('mollie-payments-for-woocommerce_test_mode_enabled', true);
        $shouldShowNotice = $testModeEnabled === 'yes';
        if (!$shouldShowNotice) {
            return false;
        }
        $notice = new AdminNotice();
        $message = sprintf(
        /* translators: Placeholder 1: Opening strong tag. Placeholder 2: Closing strong tag. Placeholder 3: Opening link tag. Placeholder 4: Closing link tag. */
            esc_html__(
                '%1$sMollie Payments for WooCommerce%2$s The test mode is active, %3$s disable it%4$s before deploying into production.',
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(
                admin_url('admin.php?page=wc-settings&tab=mollie_settings')
            ) . '">',
            '</a>'
        );
        $notice->addNotice('notice-error', $message);
        return true;
    }

    /**
     * Save default settings if not found
     * @param $optionName
     * @param $defaultOptions
     * @return void
     */
    public function maybeSaveDefaultSettings($optionName, $testOption, $defaultOptions): void
    {
        //if one exists in db, then the settings were saved
        $hasOption = get_option("{$optionName}{$testOption}");
        if ($hasOption) {
            return;
        }
        foreach ($defaultOptions as $defaultOption) {
            $noOptions = [
                "{$optionName}sectionend",
                "{$optionName}title",
                "{$optionName}styles",
                "{$optionName}invalid_styles",
            ];
            if (in_array($defaultOption['id'], $noOptions, true)) {
                continue;
            }
            update_option($defaultOption['id'], $defaultOption['default']);
        }
    }

    /**
     * @param $isSDDGatewayEnabled
     * @param $gateways
     * @param $pluginPath
     * @param $paymentMethods
     * @return void
     */
    protected function initMollieSettingsPage($isSDDGatewayEnabled, $gateways, $pluginPath, $paymentMethods): void
    {
        if (!$isSDDGatewayEnabled) {
            //remove directdebit gateway from gateways list
            unset($gateways['mollie_wc_gateway_directdebit']);
        }
        add_filter(
            'woocommerce_get_settings_pages',
            function ($settings) use ($pluginPath, $gateways, $paymentMethods) {
                $settings[] = new MollieSettingsPage(
                    $this->settingsHelper,
                    $pluginPath,
                    $gateways,
                    $paymentMethods,
                    $this->isTestModeEnabled,
                    $this->dataHelper
                );

                return $settings;
            }
        );
    }
}
