<?php

declare(strict_types=1);

use Mollie\WooCommerce\Adapter\Mollie\MollieApi;
use Mollie\WooCommerce\Adapter\Mollie\SdkMollieApi;
use Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Adapter\WordPress\SystemClock;
use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerce\Log\WcPsrLoggerAdapter;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return static function (): array {
    return [
        'express.config' => static function (): array {
            return require dirname(__DIR__, 3) . '/config/express.php';
        },
        MollieApi::class => static function (ContainerInterface $container): MollieApi {
            $api = $container->get('SDK.api_helper');
            assert($api instanceof Api);
            $settings = $container->get('settings.settings_helper');
            assert($settings instanceof Settings);

            return new SdkMollieApi($api, $settings);
        },
        Clock::class => static function (): Clock {
            return new SystemClock();
        },
        OrderLock::class => static function (): OrderLock {
            global $wpdb;

            return new OrderLock($wpdb);
        },
        EventLog::class => static function (ContainerInterface $container): EventLog {
            $logger = $container->get(LoggerInterface::class);
            assert($logger instanceof LoggerInterface);

            // With the merchant's debug switch off the plugin logger is a NullLogger, so the events
            // are written only if a site opts in. Merchant-visible behaviour is unchanged by default.
            if (!$container->get('settings.IsDebugEnabled') && apply_filters('mollie_wc_event_log_always_on', false)) {
                $logger = new WcPsrLoggerAdapter(wc_get_logger(), $container->get('shared.plugin_id') . '-');
            }

            return new EventLog($logger);
        },
        EffectInterpreter::class => static function (ContainerInterface $container): EffectInterpreter {
            $lock = $container->get(OrderLock::class);
            assert($lock instanceof OrderLock);
            $log = $container->get(EventLog::class);
            assert($log instanceof EventLog);

            return new EffectInterpreter($lock, $log);
        },
        ExpressFactsBuilder::class => static function (ContainerInterface $container): ExpressFactsBuilder {
            $settings = $container->get('settings.settings_helper');
            assert($settings instanceof Settings);

            return new ExpressFactsBuilder(
                $container->get('express.config'),
                $settings,
                $container->get('gateway.paymentMethods'),
                static function () use ($container): array {
                    return $container->get('gateway.paymentMethodsEnabledAtMollie');
                }
            );
        },
    ];
};
