<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent;

use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Core\Express\SurfaceOwnership;
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
        return \true;
    }
}
