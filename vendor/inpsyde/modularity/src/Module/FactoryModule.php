<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Module;

/**
 * @phpstan-import-type Service from ServiceModule
 */
interface FactoryModule extends Module
{
    /**
     * Return application factories.
     *
     * Similar to `services`, but object created by given factories are not "cached", but a *new*
     * instance is returned everytime `get()` is called in the container.
     *
     * @return array<string, Service>
     */
    public function factories(): array;
}
