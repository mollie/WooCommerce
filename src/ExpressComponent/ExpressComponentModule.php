<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\ExpressReturnHandler;
use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Adapter\WordPress\ExpressUrls;
use Mollie\WooCommerce\Core\Express\SurfaceOwnership;
use Mollie\WooCommerce\Workflow\ExpireAbandonedExpressOrders;
use Psr\Container\ContainerInterface;

class ExpressComponentModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        static $services;

        if ($services === null) {
            $services = require_once __DIR__ . '/inc/services.php';
        }

        return $services();
    }

    public function run(ContainerInterface $container): bool
    {
        add_filter(
            'mollie_wc_express_owns_surface',
            static function ($owns, $surface) use ($container): bool {
                $facts = $container->get(ExpressFactsBuilder::class);
                assert($facts instanceof ExpressFactsBuilder);

                return SurfaceOwnership::owns($facts->settings(), $facts->shopFacts(), (string) $surface);
            },
            10,
            2
        );

        add_action('rest_api_init', static function () use ($container): void {
            $routes = $container->get(ExpressRoutes::class);
            assert($routes instanceof ExpressRoutes);
            $routes->register();
        });

        add_action('woocommerce_api_' . ExpressUrls::RETURN_API, static function () use ($container): void {
            $handler = $container->get(ExpressReturnHandler::class);
            assert($handler instanceof ExpressReturnHandler);
            $handler->handle();
        });

        // Runs on the plugin's existing cleanup action, which PaymentModule keeps scheduled while
        // Express is enabled (see 'express.enabled').
        add_action('mollie_woocommerce_cancel_unpaid_orders', static function () use ($container): void {
            $cleanup = $container->get(ExpireAbandonedExpressOrders::class);
            assert($cleanup instanceof ExpireAbandonedExpressOrders);
            $cleanup->run();
        }, 12, 0);

        return true;
    }
}
