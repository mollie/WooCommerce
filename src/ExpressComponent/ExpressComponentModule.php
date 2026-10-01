<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;

/**
 * Composition root of the Express Component, and nothing else: it wires the seed's adapters and
 * registers no hooks yet.
 */
class ExpressComponentModule implements ServiceModule
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
