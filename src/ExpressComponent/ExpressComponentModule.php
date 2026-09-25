<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Core\Express\SurfaceOwnership;
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

        return true;
    }
}
