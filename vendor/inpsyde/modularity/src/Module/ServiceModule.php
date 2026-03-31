<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Module;

use Mollie\Psr\Container\ContainerInterface;
/**
 * @phpstan-type Service callable(ContainerInterface $container): mixed
 */
interface ServiceModule extends Module
{
    /**
     * Return application services' factories.
     *
     * Array keys will be services' IDs in the container, array values are callback that
     * accepts a PSR-11 container as parameter and return an instance of the service.
     * Services are "cached", so the given factory is called once the first time `get()` is called
     * in the container, and on subsequent `get()` the same instance is returned again and again.
     *
     * @return array<string, Service>
     */
    public function services(): array;
}
