<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Settings\Page\MollieSettingsPage;
use Psr\Container\ContainerInterface;

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
            'settings.getGlobalSettingsUrl' => function (ContainerInterface $container): string {
                /** @var Settings $settingsHelper */
                $pluginId = $container->get('core.plugin_id');
                return admin_url('admin.php?page=wc-settings&tab=mollie_settings#' . $pluginId);
            },
            'settings.settings_helper' => function (ContainerInterface $container): Settings {
                $pluginId = $container->get('core.plugin_id');
                $pluginUrl = $container->get('core.plugin_url');
                $statusHelper = $container->get('core.status_helper');
                $pluginVersion = $container->get('core.plugin_version');
                $apiHelper =  $container->get('core.api_helper');
                $globalSettingsUrl = $container->get('settings.getGlobalSettingsUrl');
                return new Settings(
                    $pluginId,
                    $statusHelper,
                    $pluginVersion,
                    $pluginUrl,
                    $apiHelper,
                    $globalSettingsUrl
                );
            },
            'settings.payment_method_settings_handler' => function (): PaymentMethodSettingsHandler {
                return new PaymentMethodSettingsHandler();
            },
            'settings.IsTestModeEnabled' => function (ContainerInterface $container): bool {
                /** @var Settings $settingsHelper */
                $settingsHelper = $container->get('settings.settings_helper');
                return $settingsHelper->isTestModeEnabled();
            },


        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $this->plugin_basename = $container->get('core.plugin_file');
        $this->settingsHelper = $container->get('settings.settings_helper');
        $this->isTestModeEnabled = $container->get('settings.IsTestModeEnabled');
        $this->dataHelper = $container->get('core.data_helper');
        $pluginPath = $container->get('core.plugin_path');
        $gateways = $container->get('gateway.instances');
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'addPluginActionLinks']);

        add_action('wp_loaded', function () {
            $this->maybeTestModeNotice($this->isTestModeEnabled);
        });
        add_filter(
            'woocommerce_get_settings_pages',
            function ($settings) use ($pluginPath, $gateways) {
                $settings[] = new MollieSettingsPage(
                    $this->settingsHelper,
                    $pluginPath,
                    $gateways,
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
