<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Module;

use Mollie\Psr\Container\ContainerInterface;
/**
 * @phpstan-type ExtendingService callable(mixed $service, ContainerInterface $container): mixed
 */
interface ExtendingModule extends Module
{
    /**
     * Return application services' extensions.
     *
     * Array keys will be services' IDs in the container, array values are callback that
     * accepts as parameters the original service and a PSR-11 container and return an instance of
     * the extended service.
     *
     * It is possible to explicitly extend extensions made by other modules.
     * That is done by using as ID (array key in the `extensions` method) the target module ID
     * and the service ID.
     *
     * @return array<string, ExtendingService>
     */
    public function extensions(): array;
}
