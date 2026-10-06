<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressBlocksData;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactory;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionBudget;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressAssets;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressReturnHandler;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressUrls;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\Shared\Clock;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\ExpressComponent\Flow\ExpireAbandonedExpressOrders;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\OrphanedExpressPayments;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\PendingExpressOrders;
use Mollie\WooCommerce\ExpressComponent\Flow\ResolveExpressPayment;
use Mollie\WooCommerce\ExpressComponent\Flow\StartExpressOrder;
use Mollie\WooCommerce\ExpressComponent\Flow\StartExpressSession;
use Mollie\Psr\Container\ContainerInterface;
return static function (): array {
    return [
        'express.config' => static function (): array {
            return require \dirname(__DIR__, 3) . '/config/express.php';
        },
        ExpressOrderWriter::class => static function (ContainerInterface $container): ExpressOrderWriter {
            return new ExpressOrderWriter($container->get(ProcessRecordStore::class));
        },
        ExpressFactsBuilder::class => static function (ContainerInterface $container): ExpressFactsBuilder {
            $settings = $container->get('settings.settings_helper');
            \assert($settings instanceof Settings);
            return new ExpressFactsBuilder($container->get('express.config'), $settings, $container->get('gateway.paymentMethods'), static function () use ($container): array {
                return $container->get('gateway.paymentMethodsEnabledAtMollie');
            });
        },
        CartFactsBuilder::class => static function (): CartFactsBuilder {
            return new CartFactsBuilder();
        },
        ExpressSessionStore::class => static function (): ExpressSessionStore {
            return new ExpressSessionStore();
        },
        ExpressSessionBudget::class => static function (ContainerInterface $container): ExpressSessionBudget {
            $config = $container->get('express.config');
            return new ExpressSessionBudget((int) $config['maxNewSessions'], (int) $config['maxNewSessionsPerAddress'], (int) $config['windowSeconds']);
        },
        ExpressUrls::class => static function (ContainerInterface $container): ExpressUrls {
            $secret = $container->get(WebhookSecret::class);
            \assert($secret instanceof WebhookSecret);
            return new ExpressUrls($secret);
        },
        StartExpressSession::class => static function (ContainerInterface $container): StartExpressSession {
            return new StartExpressSession($container->get(CartFactsBuilder::class), $container->get(ExpressFactsBuilder::class), $container->get(ExpressSessionStore::class), $container->get(ExpressSessionBudget::class), $container->get(ExpressUrls::class), $container->get(MollieApi::class), $container->get(Clock::class), $container->get(EventLog::class), (int) $container->get('express.config')['sessionReuseMarginSeconds']);
        },
        PendingExpressOrders::class => static function (): PendingExpressOrders {
            return new PendingExpressOrders();
        },
        // Options only: called on every init.
        'express.cleanup_needed' => static function (ContainerInterface $container): callable {
            return static function () use ($container): bool {
                $facts = $container->get(ExpressFactsBuilder::class);
                \assert($facts instanceof ExpressFactsBuilder);
                $pending = $container->get(PendingExpressOrders::class);
                \assert($pending instanceof PendingExpressOrders);
                return $facts->anyWalletTurnedOn() || $pending->mayExist();
            };
        },
        ExpressOrderFactsBuilder::class => static function (ContainerInterface $container): ExpressOrderFactsBuilder {
            return new ExpressOrderFactsBuilder($container->get(ExpressSessionStore::class), $container->get(ProcessRecordStore::class));
        },
        ExpressOrderFactory::class => static function (ContainerInterface $container): ExpressOrderFactory {
            return new ExpressOrderFactory($container->get(PendingExpressOrders::class));
        },
        StartExpressOrder::class => static function (ContainerInterface $container): StartExpressOrder {
            return new StartExpressOrder($container->get(CartFactsBuilder::class), $container->get(ExpressFactsBuilder::class), $container->get(ExpressOrderFactsBuilder::class), $container->get(ExpressSessionStore::class), $container->get(ExpressOrderFactory::class), $container->get(ExpressOrderWriter::class), $container->get(OrderLock::class), $container->get(Clock::class), $container->get(EventLog::class));
        },
        ExpressReturnHandler::class => static function (ContainerInterface $container): ExpressReturnHandler {
            return new ExpressReturnHandler($container->get(ExpressOrderFactsBuilder::class), $container->get(EventLog::class));
        },
        ExpireAbandonedExpressOrders::class => static function (ContainerInterface $container): ExpireAbandonedExpressOrders {
            return new ExpireAbandonedExpressOrders($container->get(ExpressOrderFactsBuilder::class), $container->get(MollieApi::class), $container->get(OrderLock::class), $container->get(ExpressOrderWriter::class), $container->get(Clock::class), $container->get(EventLog::class), $container->get(PendingExpressOrders::class), $container->get(ExpressFactsBuilder::class), (int) $container->get('express.config')['abandonGraceSeconds']);
        },
        OrphanedExpressPayments::class => static function (): OrphanedExpressPayments {
            return new OrphanedExpressPayments();
        },
        ResolveExpressPayment::class => static function (ContainerInterface $container): ResolveExpressPayment {
            return new ResolveExpressPayment($container->get(MollieApi::class), $container->get(ExpressOrderFactsBuilder::class), $container->get(OrderLock::class), $container->get(ExpressOrderWriter::class), $container->get(EventLog::class), $container->get(OrphanedExpressPayments::class), $container->get('express.config')['wallets'], static function (): array {
                return \array_keys(\WC()->payment_gateways()->payment_gateways());
            });
        },
        ExpressBlocksData::class => static function (): ExpressBlocksData {
            return new ExpressBlocksData();
        },
        ExpressAssets::class => static function (ContainerInterface $container): ExpressAssets {
            $facts = $container->get(ExpressFactsBuilder::class);
            \assert($facts instanceof ExpressFactsBuilder);
            $blocksData = $container->get(ExpressBlocksData::class);
            \assert($blocksData instanceof ExpressBlocksData);
            return new ExpressAssets($facts, $blocksData);
        },
        ExpressRoutes::class => static function (ContainerInterface $container): ExpressRoutes {
            return new ExpressRoutes($container->get(StartExpressSession::class), $container->get(StartExpressOrder::class), $container->get(EventLog::class));
        },
    ];
};
