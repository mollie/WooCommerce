<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Components;

use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Psr\Container\ContainerInterface;
class ComponentsModule implements ServiceModule
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
}
