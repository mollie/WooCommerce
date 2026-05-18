<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Tracks;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Settings\Settings;
use Psr\Container\ContainerInterface;

class TracksModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;

    public const OPTION_FIRST_TEST_PAYMENT_TRACKED = 'mollie_tracks_first_test_payment_tracked';
    public const OPTION_API_KEYS_VIEWED = 'mollie_tracks_api_keys_viewed';
    private const OPTION_PLUGIN_ACTIVATED = 'mollie_tracks_plugin_activated';
    private const OPTION_PREFIX = 'mollie-payments-for-woocommerce_';
    private const PAYMENT_MODE_TEST = 'test';

    private const ALL_TRACKING_OPTIONS = [
        self::OPTION_FIRST_TEST_PAYMENT_TRACKED,
        self::OPTION_API_KEYS_VIEWED,
        self::OPTION_PLUGIN_ACTIVATED,
    ];

    public function services(): array
    {
        return [
            TracksEventRecorder::class => static function (ContainerInterface $container): TracksEventRecorder {
                $pluginVersion = $container->get('shared.plugin_version');
                return new TracksEventRecorder($pluginVersion);
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $recorder = $container->get(TracksEventRecorder::class);
        assert($recorder instanceof TracksEventRecorder);

        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof Settings);

        $pluginId = $container->get('shared.plugin_id');

        $this->trackDeferredPluginActivation($recorder);
        $this->trackApiKeysViewed($recorder);
        $this->trackApiKeySaved($recorder, $settingsHelper);
        $this->trackTestPaymentComplete($recorder, $pluginId);

        return true;
    }

    /**
     * Set flag on plugin activation for deferred tracking.
     *
     * Must be called via register_activation_hook since the activation
     * request does not boot the module system.
     */
    public static function onPluginActivation(): void
    {
        update_option(self::OPTION_PLUGIN_ACTIVATED, '1', false);
    }

    /**
     * Reset tracking state on deactivation if the merchant was not yet connected.
     *
     * Must be called via register_deactivation_hook.
     */
    public static function onPluginDeactivation(): void
    {
        $hasTestKey = (bool) get_option(self::OPTION_PREFIX . 'test_api_key');
        $hasLiveKey = (bool) get_option(self::OPTION_PREFIX . 'live_api_key');

        // Skip reset if merchant was connected (has API keys)
        if ($hasTestKey || $hasLiveKey) {
            return;
        }

        foreach (self::ALL_TRACKING_OPTIONS as $option) {
            delete_option($option);
        }
    }

    /**
     * Fire plugin_activated on next admin request after activation.
     */
    private function trackDeferredPluginActivation(TracksEventRecorder $recorder): void
    {
        add_action('admin_init', static function () use ($recorder): void {
            // Skip if no activation flag (not freshly activated)
            if (!get_option(self::OPTION_PLUGIN_ACTIVATED)) {
                return;
            }

            delete_option(self::OPTION_PLUGIN_ACTIVATED);

            $recorder->recordEvent('wcadmin_mollie_plugin_activated');
        }, 20);
    }

    /**
     * Track when the API keys settings tab is viewed.
     */
    private function trackApiKeysViewed(TracksEventRecorder $recorder): void
    {
        add_action('woocommerce_settings_mollie_settings', static function () use ($recorder): void {
            // Skip if already tracked once (one-time event)
            if (get_option(self::OPTION_API_KEYS_VIEWED)) {
                return;
            }

            $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

            // Skip non-API-keys sections (empty defaults to api_keys)
            if ($section !== 'mollie_api_keys' && $section !== '') {
                return;
            }

            update_option(self::OPTION_API_KEYS_VIEWED, '1', false);

            $recorder->recordEvent('wcadmin_mollie_api_keys_viewed');
        });
    }

    /**
     * Track API keys saved and connection result.
     */
    private function trackApiKeySaved(
        TracksEventRecorder $recorder,
        Settings $settingsHelper
    ): void {
        add_action('woocommerce_settings_saved', static function () use ($recorder, $settingsHelper): void {
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
            $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
            $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
            // Skip if not on the Mollie settings page
            if ($page !== 'wc-settings' || $tab !== 'mollie_settings') {
                return;
            }
            // Skip non-API-keys sections (empty defaults to api_keys)
            if ($section !== '' && $section !== 'mollie_api_keys') {
                return;
            }

            $testKeyOption = self::OPTION_PREFIX . 'test_api_key';
            $liveKeyOption = self::OPTION_PREFIX . 'live_api_key';

            $paymentMode = $settingsHelper->isTestModeEnabled() ? 'test' : 'live';

            $recorder->recordEvent('wcadmin_mollie_api_key_saved', [
                'payment_mode' => $paymentMode,
                'has_test_key' => (bool) get_option($testKeyOption),
                'has_live_key' => (bool) get_option($liveKeyOption),
            ]);

            $result = $settingsHelper->getConnectionStatusWithError();

            if ($result['connected']) {
                $recorder->recordEvent('wcadmin_mollie_connection_success', [
                    'payment_mode' => $paymentMode,
                ]);
                return;
            }

            $recorder->recordEvent('wcadmin_mollie_connection_failed', [
                'payment_mode' => $paymentMode,
                'error_code' => $result['error_code'] ?? 0,
                'error_message' => preg_replace('/^(\[[\d\-T:+]+\]\s*)+/', '', $result['error_message'] ?? ''),
            ]);
        });
    }

    /**
     * Track first test payment completed via a Mollie webhook.
     */
    private function trackTestPaymentComplete(TracksEventRecorder $recorder, string $pluginId): void
    {
        add_action(
            $pluginId . '_after_webhook_action',
            static function (object $payment, $order) use ($recorder): void {
                // Skip if first test payment already tracked (one-time event)
                if (get_option(self::OPTION_FIRST_TEST_PAYMENT_TRACKED)) {
                    return;
                }

                // Skip unpaid/pending payments
                if (!$payment->isPaid()) {
                    return;
                }

                // Skip live payments (only track test mode)
                if ($payment->mode !== self::PAYMENT_MODE_TEST) {
                    return;
                }

                update_option(self::OPTION_FIRST_TEST_PAYMENT_TRACKED, '1', false);

                $recorder->recordEvent('wcadmin_mollie_first_test_payment_complete', [
                    'payment_method' => $payment->method ?? '',
                ]);
            },
            10,
            2
        );
    }
}
