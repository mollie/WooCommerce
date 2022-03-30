<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Page\MollieSettingsPage;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Uninstall\CleanDb;
use Psr\Container\ContainerInterface;
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

    public function services(): array
    {
        return [
            'settings.settings_helper' => static function (ContainerInterface $container): Settings {
                $pluginId = $container->get('shared.plugin_id');
                $pluginUrl = $container->get('shared.plugin_url');
                $statusHelper = $container->get('shared.status_helper');
                $pluginVersion = $container->get('shared.plugin_version');
                $apiHelper =  $container->get('SDK.api_helper');
                $cleanDb = $container->get(CleanDb::class);
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
                /** @var Api $apiHelper */
                $apiHelper = $container->get('SDK.api_helper');
                $logger = $container->get(Logger::class);
                $pluginId = $container->get('shared.plugin_id');
                $pluginPath = $container->get('shared.plugin_path');
                $settings = $container->get('settings.settings_helper');
                return new Data($apiHelper, $logger, $pluginId, $settings, $pluginPath);
            },
            'settings.IsTestModeEnabled' => static function (ContainerInterface $container): bool {
                /** @var Settings $settingsHelper */
                $settingsHelper = $container->get('settings.settings_helper');
                return $settingsHelper->isTestModeEnabled();
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $this->plugin_basename = $container->get('shared.plugin_file');
        $this->settingsHelper = $container->get('settings.settings_helper');
        $this->isTestModeEnabled = $container->get('settings.IsTestModeEnabled');
        $this->dataHelper = $container->get('settings.data_helper');
        $pluginPath = $container->get('shared.plugin_path');
        $gateways = $container->get('gateway.instances');
        $paymentMethods = $container->get('gateway.paymentMethods');
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'addPluginActionLinks']);

        add_action('wp_loaded', function () {
            $this->maybeTestModeNotice($this->isTestModeEnabled);
        });

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

        // When page 'WooCommerce -> Checkout -> Checkout Options' is saved
        add_action('woocommerce_settings_save_checkout', [$this->dataHelper, 'deleteTransients']);

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

    public function maybeTestModeNotice($isTestModeEnabled)
    {
        if ($isTestModeEnabled) {
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
        }
    }


}
