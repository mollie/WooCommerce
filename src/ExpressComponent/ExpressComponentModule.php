<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent;

use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Adapter\WordPress\OrphanedExpressPayments;
use Mollie\WooCommerce\Adapter\WordPress\ExpressAssets;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\ExpressReturnHandler;
use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Adapter\WordPress\ExpressUrls;
use Mollie\WooCommerce\Core\Express\SurfaceOwnership;
use Mollie\WooCommerce\Workflow\ExpireAbandonedExpressOrders;
use Mollie\Psr\Container\ContainerInterface;
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
        add_filter('mollie_wc_express_owns_surface', static function ($owns, $surface) use ($container): bool {
            $facts = $container->get(ExpressFactsBuilder::class);
            assert($facts instanceof ExpressFactsBuilder);
            return SurfaceOwnership::owns($facts->settings(), $facts->shopFacts(), (string) $surface);
        }, 10, 2);
        add_action('rest_api_init', static function () use ($container): void {
            $routes = $container->get(ExpressRoutes::class);
            assert($routes instanceof ExpressRoutes);
            $routes->register();
        });
        // Mollie.js v2 and mollieExpressData, on a block checkout that Express owns only.
        add_action('wp_enqueue_scripts', static function () use ($container): void {
            $assets = $container->get(ExpressAssets::class);
            assert($assets instanceof ExpressAssets);
            $assets->enqueue();
        });
        add_action('woocommerce_api_' . ExpressUrls::RETURN_API, static function () use ($container): void {
            $handler = $container->get(ExpressReturnHandler::class);
            assert($handler instanceof ExpressReturnHandler);
            $handler->handle();
        });
        // Discovered in a webhook, which is no place to show anything: the notice waits for an
        // administrator to load a page.
        add_action('admin_notices', static function () use ($container): void {
            $orphaned = $container->get(OrphanedExpressPayments::class);
            assert($orphaned instanceof OrphanedExpressPayments);
            $orphaned->renderNotice();
        });
        // Runs on the plugin's existing cleanup action, which PaymentModule keeps scheduled while
        // Express is enabled (see 'express.enabled').
        add_action('mollie_woocommerce_cancel_unpaid_orders', static function () use ($container): void {
            $cleanup = $container->get(ExpireAbandonedExpressOrders::class);
            assert($cleanup instanceof ExpireAbandonedExpressOrders);
            $cleanup->run();
        }, 12, 0);
        return \true;
    }
}
