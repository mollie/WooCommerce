<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services;

use Mollie\Dhii\Services\Factories\Constructor;
use Mollie\Psr\Container\ContainerInterface;
/**
 * A simple implementation for a factory service.
 *
 * This implementation will automatically resolve any specified dependencies and pass them as arguments to the
 * definition function. The container will NOT be included in the arguments.
 *
 * Example usage:
 * ```
 * new Factory(['foo', 'bar'], function($foo, $bar) {
 *      return new SomeClass($foo, $bar);
 * });
 * ```
 *
 * @see   Constructor For a similar implementation that automatically injects dependencies into constructors.
 * @see   Extension For a similar implementation that can be used with extension services.
 */
class Factory extends Service
{
    use ResolveKeysCapableTrait;
    /** @var callable */
    protected $definition;
    /**
     * @inheritDoc
     *
     * @param callable $definition The factory definition.
     */
    public function __construct(array $dependencies, callable $definition)
    {
        parent::__construct($dependencies);
        $this->definition = $definition;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $c)
    {
        $deps = $this->resolveKeys($c, $this->dependencies);
        return ($this->definition)(...$deps);
    }
}
