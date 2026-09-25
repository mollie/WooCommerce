<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\WooCommerce\Adapter\Mollie\MollieApi;
use Mollie\WooCommerce\Adapter\Mollie\SdkMollieApi;
use Mollie\WooCommerce\Adapter\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressSessionBudget;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Adapter\WordPress\ExpressUrls;
use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Adapter\WordPress\SystemClock;
use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerce\Log\WcPsrLoggerAdapter;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Workflow\StartExpressSession;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface;
return static function (): array {
    return ['express.config' => static function (): array {
        return require \dirname(__DIR__, 3) . '/config/express.php';
    }, MollieApi::class => static function (ContainerInterface $container): MollieApi {
        $api = $container->get('SDK.api_helper');
        \assert($api instanceof Api);
        $settings = $container->get('settings.settings_helper');
        \assert($settings instanceof Settings);
        return new SdkMollieApi($api, $settings);
    }, Clock::class => static function (): Clock {
        return new SystemClock();
    }, OrderLock::class => static function (): OrderLock {
        global $wpdb;
        return new OrderLock($wpdb);
    }, EventLog::class => static function (ContainerInterface $container): EventLog {
        $logger = $container->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);
        // With the merchant's debug switch off the plugin logger is a NullLogger, so the events
        // are written only if a site opts in. Merchant-visible behaviour is unchanged by default.
        if (!$container->get('settings.IsDebugEnabled') && \apply_filters('mollie_wc_event_log_always_on', \false)) {
            $logger = new WcPsrLoggerAdapter(\wc_get_logger(), $container->get('shared.plugin_id') . '-');
        }
        return new EventLog($logger);
    }, EffectInterpreter::class => static function (ContainerInterface $container): EffectInterpreter {
        $lock = $container->get(OrderLock::class);
        \assert($lock instanceof OrderLock);
        $log = $container->get(EventLog::class);
        \assert($log instanceof EventLog);
        return new EffectInterpreter($lock, $log);
    }, ExpressFactsBuilder::class => static function (ContainerInterface $container): ExpressFactsBuilder {
        $settings = $container->get('settings.settings_helper');
        \assert($settings instanceof Settings);
        return new ExpressFactsBuilder($container->get('express.config'), $settings, $container->get('gateway.paymentMethods'), static function () use ($container): array {
            return $container->get('gateway.paymentMethodsEnabledAtMollie');
        });
    }, CartFactsBuilder::class => static function (): CartFactsBuilder {
        return new CartFactsBuilder();
    }, ExpressSessionStore::class => static function (): ExpressSessionStore {
        return new ExpressSessionStore();
    }, ExpressSessionBudget::class => static function (ContainerInterface $container): ExpressSessionBudget {
        $config = $container->get('express.config');
        return new ExpressSessionBudget((int) $config['maxNewSessions'], (int) $config['windowSeconds']);
    }, ExpressUrls::class => static function (ContainerInterface $container): ExpressUrls {
        $secret = $container->get(WebhookSecret::class);
        \assert($secret instanceof WebhookSecret);
        return new ExpressUrls($secret);
    }, StartExpressSession::class => static function (ContainerInterface $container): StartExpressSession {
        return new StartExpressSession($container->get(CartFactsBuilder::class), $container->get(ExpressFactsBuilder::class), $container->get(ExpressSessionStore::class), $container->get(ExpressSessionBudget::class), $container->get(ExpressUrls::class), $container->get(MollieApi::class), $container->get(Clock::class), $container->get(EventLog::class), (int) $container->get('express.config')['sessionReuseMarginSeconds']);
    }, ExpressRoutes::class => static function (ContainerInterface $container): ExpressRoutes {
        return new ExpressRoutes($container->get(StartExpressSession::class), $container->get(EventLog::class));
    }];
};
