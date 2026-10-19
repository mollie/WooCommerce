<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\OrphanedExpressPayments;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressAssets;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressReturnHandler;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressUrls;
use Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership;
use Mollie\WooCommerce\ExpressComponent\Flow\ExpireAbandonedExpressOrders;
use Mollie\WooCommerce\Log\EventLog;
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
        // Decided once per request and surface, from the cart as the page is rendered.
        $owned = [];
        add_filter(
            'mollie_wc_express_owns_surface',
            static function ($owns, $surface) use ($container, &$owned): bool {
                $surface = (string) $surface;
                $owned[$surface] ??= self::owns($container, $surface);

                return $owned[$surface];
            },
            10,
            2
        );

        add_action('rest_api_init', static function () use ($container): void {
            $routes = $container->get(ExpressRoutes::class);
            assert($routes instanceof ExpressRoutes);
            $routes->register();
        });

        add_action('wp_enqueue_scripts', static function () use ($container, &$owned): void {
            $assets = $container->get(ExpressAssets::class);
            assert($assets instanceof ExpressAssets);
            $assets->enqueue(static function () use ($container, &$owned): bool {
                $owned[ExpressAssets::SURFACE] ??= self::owns($container, ExpressAssets::SURFACE);

                return $owned[ExpressAssets::SURFACE];
            });
        });

        add_action('woocommerce_api_' . ExpressUrls::RETURN_API, static function () use ($container): void {
            $handler = $container->get(ExpressReturnHandler::class);
            assert($handler instanceof ExpressReturnHandler);
            $handler->handle();
        });

        add_action('admin_notices', static function () use ($container): void {
            $orphaned = $container->get(OrphanedExpressPayments::class);
            assert($orphaned instanceof OrphanedExpressPayments);
            $orphaned->renderNotice();
        });

        add_action('shutdown', static function () use ($container): void {
            $log = $container->get(EventLog::class);
            assert($log instanceof EventLog);
            // A fatal error skips the flows' finally.
            $error = error_get_last();
            $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
            $log->flush(is_array($error) && ($error['type'] & $fatal) !== 0);
        });

        // PaymentModule schedules this action.
        add_action('mollie_woocommerce_cancel_unpaid_orders', static function () use ($container): void {
            $cleanup = $container->get(ExpireAbandonedExpressOrders::class);
            assert($cleanup instanceof ExpireAbandonedExpressOrders);
            $cleanup->run();
        }, 12, 0);

        return true;
    }

    /**
     * A cart WooCommerce has not loaded is not owned.
     */
    private static function owns(ContainerInterface $container, string $surface): bool
    {
        $facts = $container->get(ExpressFactsBuilder::class);
        assert($facts instanceof ExpressFactsBuilder);
        $carts = $container->get(CartFactsBuilder::class);
        assert($carts instanceof CartFactsBuilder);
        $cart = $carts->fromCart();
        if ($cart === null) {
            return false;
        }

        return SurfaceOwnership::owns($facts->settings(), $facts->shopFacts(), $cart, $surface);
    }
}
