<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Settings;

use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\Status;
use Mollie\WooCommerce\Uninstall\CleanDb;
use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface as Logger;
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
            'settings.settings_helper' => static function (ContainerInterface $container): \Mollie\WooCommerce\Settings\Settings {
                $pluginId = $container->get('shared.plugin_id');
                $pluginUrl = $container->get('shared.plugin_url');
                $statusHelper = $container->get('shared.status_helper');
                assert($statusHelper instanceof Status);
                $pluginVersion = $container->get('shared.plugin_version');
                $apiHelper = $container->get('SDK.api_helper');
                assert($apiHelper instanceof Api);
                $cleanDb = $container->get(CleanDb::class);
                assert($cleanDb instanceof CleanDb);
                return new \Mollie\WooCommerce\Settings\Settings($pluginId, $statusHelper, $pluginVersion, $pluginUrl, $apiHelper, $cleanDb);
            },
            'settings.data_helper' => static function (ContainerInterface $container): Data {
                $apiHelper = $container->get('SDK.api_helper');
                assert($apiHelper instanceof Api);
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                $pluginId = $container->get('shared.plugin_id');
                $pluginPath = $container->get('shared.plugin_path');
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof \Mollie\WooCommerce\Settings\Settings);
                return new Data($apiHelper, $logger, $pluginId, $settings, $pluginPath);
            },
            'settings.IsTestModeEnabled' => static function (ContainerInterface $container): bool {
                $settingsHelper = $container->get('settings.settings_helper');
                assert($settingsHelper instanceof \Mollie\WooCommerce\Settings\Settings);
                return $settingsHelper->isTestModeEnabled();
            },
            'settings.IsDebugEnabled' => static function (): bool {
                $debugEnabled = get_option('mollie-payments-for-woocommerce_debug', 'yes');
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
            /**
             * Webhook Test Service
             * Handles webhook connection testing functionality
             */
            WebhookTestService::class => static function (ContainerInterface $container): WebhookTestService {
                $apiHelper = $container->get('SDK.api_helper');
                assert($apiHelper instanceof Api);
                $settingsHelper = $container->get('settings.settings_helper');
                assert($settingsHelper instanceof \Mollie\WooCommerce\Settings\Settings);
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                return new WebhookTestService($apiHelper, $settingsHelper, $logger);
            },
        ];
    }
    public function run(ContainerInterface $container): bool
    {
        $this->plugin_basename = $container->get('shared.plugin_file');
        $this->settingsHelper = $container->get('settings.settings_helper');
        assert($this->settingsHelper instanceof \Mollie\WooCommerce\Settings\Settings);
        $this->isTestModeEnabled = $container->get('settings.IsTestModeEnabled');
        $this->dataHelper = $container->get('settings.data_helper');
        assert($this->dataHelper instanceof Data);
        $pluginPath = $container->get('shared.plugin_path');
        $pluginUrl = $container->get('shared.plugin_url');
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'addPluginActionLinks']);
        //add campaign to signup URL Todo REMOVE after 10th Dec 2025
        add_filter('gettext', static function ($translated, $text, $domain) {
            $dateNow = new \DateTime();
            $endDateCampaign = new \DateTime('2025-12-10');
            if ($endDateCampaign < $dateNow) {
                return $translated;
            }
            if ($domain !== 'mollie-payments-for-woocommerce') {
                return $translated;
            }
            if ($text !== "Don’t have a Mollie account yet? <a href='%s' target='_blank'>Get started with Mollie today.</a>") {
                return $translated;
            }
            return __('Don’t have a Mollie account yet? Create one now. Limited time only! Pay ZERO processing fees for your first month. <a href="%s" target="_blank">Get started with Mollie today.</a> ', 'mollie-payments-for-woocommerce');
        }, 10, 3);
        /*add_filter('mollie-payments-for-woocommerce_signup_url', static function ($url) {
                    $dateNow = new \DateTime();
                    $endDateCampaign = new \DateTime('2026-01-01');
                    if ($endDateCampaign < $dateNow) {
                        return $url;
                    }
        
                    return 'https://my.mollie.com/dashboard/signup?utm_campaign=GLO_Q4__Woo-Signup-tracker&utm_medium=referral&utm_source={woodashboard}&campaign_name=GLO_Q4__Woo-Signup-tracker';
                });*/
        //init settings with advanced and components defaults if not exists
        $optionName = $container->get('settings.option_name');
        $defaultAdvancedOptions = $container->get('settings.advanced_default_options');
        $defaultComponentsOptions = $container->get('settings.components_default_options');
        add_action('init', function () use ($optionName, $defaultAdvancedOptions, $defaultComponentsOptions) {
            $testedOption = 'order_status_cancelled_payments';
            $this->maybeSaveDefaultSettings($optionName, $testedOption, $defaultAdvancedOptions);
            $testedOption = 'backgroundColor';
            $this->maybeSaveDefaultSettings('mollie_components_', $testedOption, $defaultComponentsOptions);
        }, 10, 2);
        $this->isTestNoticePrinted = \false;
        add_action('woocommerce_settings_saved', function () {
            $isNoticePrinted = $this->maybeTestModeNotice();
            if ($isNoticePrinted) {
                $this->isTestNoticePrinted = \true;
            }
        });
        add_action('admin_init', function () {
            if ($this->isTestNoticePrinted) {
                return;
            }
            $this->maybeTestModeNotice();
        });
        if (is_admin()) {
            if (isset($_GET['refresh-methods']) && isset($_GET['nonce_mollie_refresh_methods']) && wp_verify_nonce(filter_input(\INPUT_GET, 'nonce_mollie_refresh_methods', \FILTER_SANITIZE_SPECIAL_CHARS), 'nonce_mollie_refresh_methods')) {
                $apiKey = $this->settingsHelper->getApiKey();
                $this->dataHelper->getAllPaymentMethods($apiKey, $this->isTestModeEnabled, \false);
            }
            add_filter('woocommerce_get_settings_pages', function ($settings) use ($pluginPath, $pluginUrl, $container) {
                $settings[] = new \Mollie\WooCommerce\Settings\MollieSettingsPage($this->settingsHelper, $pluginPath, $pluginUrl, $this->isTestModeEnabled, $this->dataHelper, $container);
                return $settings;
            });
        }
        add_action('woocommerce_admin_settings_sanitize_option', [$this->settingsHelper, 'updateMerchantIdOnApiKeyChanges'], 10, 2);
        add_action('update_option_mollie-payments-for-woocommerce_live_api_key', function ($oldValue, $value, $optionName) {
            $this->settingsHelper->updateMerchantIdAfterApiKeyChanges($oldValue, $value, $optionName);
            if ($oldValue !== $value) {
                $this->dataHelper->getAllPaymentMethods($value, \false, \false);
            }
        }, 10, 3);
        add_action('update_option_mollie-payments-for-woocommerce_test_api_key', function ($oldValue, $value, $optionName) {
            $this->settingsHelper->updateMerchantIdAfterApiKeyChanges($oldValue, $value, $optionName);
            if ($oldValue !== $value) {
                $this->dataHelper->getAllPaymentMethods($value, \true, \false);
            }
        }, 10, 3);
        $webhookTestService = $container->get(WebhookTestService::class);
        assert($webhookTestService instanceof WebhookTestService);
        $webhookTestService->registerAjaxHandlers();
        return \true;
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
            '<a href="' . $this->settingsHelper->getGlobalSettingsUrl() . '">' . __('Mollie settings', 'mollie-payments-for-woocommerce') . '</a>',
        ];
        // Add link to WooCommerce logs
        $action_links[] = '<a href="' . $this->settingsHelper->getLogsUrl() . '">' . __('Logs', 'mollie-payments-for-woocommerce') . '</a>';
        return array_merge($action_links, $links);
    }
    public function maybeTestModeNotice(): bool
    {
        $testModeEnabled = get_option('mollie-payments-for-woocommerce_test_mode_enabled', \true);
        $testKeyEntered = get_option('mollie-payments-for-woocommerce_test_api_key', \true);
        $shouldShowNotice = $testModeEnabled === 'yes' && !empty($testKeyEntered);
        if (!$shouldShowNotice) {
            return \false;
        }
        $notice = new AdminNotice();
        $message = sprintf(
            /* translators: Placeholder 1: Opening strong tag. Placeholder 2: Closing strong tag. Placeholder 3: Opening link tag. Placeholder 4: Closing link tag. */
            esc_html__('%1$sMollie Payments for WooCommerce%2$s The test mode is active, %3$s disable it%4$s before deploying into production.', 'mollie-payments-for-woocommerce'),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=mollie_settings')) . '">',
            '</a>'
        );
        $notice->addNotice('notice-error', $message);
        return \true;
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
            $noOptions = ["{$optionName}sectionend", "{$optionName}title", "{$optionName}styles", "{$optionName}invalid_styles"];
            if (in_array($defaultOption['id'], $noOptions, \true)) {
                continue;
            }
            update_option($defaultOption['id'], $defaultOption['default']);
        }
    }
}
