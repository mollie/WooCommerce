<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services;

use Mollie\Psr\Container\ContainerInterface;
/**
 * Partial implementation of a service.
 *
 * This class represents a DI service in functor form. Instances of this class or its derivations are invocable via
 * the magic {@link __invoke()} method. The signature of this method is equivalent to that of regular service definition
 * functions, in that they accept a {@link ContainerInterface} instance.
 *
 * Services are also aware of any services that they depend on, by key. These keys may be used by derivations of this
 * class to achieve some automation, be it during run-time or for static analysis purposes.
 *
 * @see   __invoke()
 * @see   ContainerInterface
 */
abstract class Service
{
    /**
     * @var string[]
     */
    protected $dependencies;
    /**
     * Constructor.
     *
     * @param string[] $dependencies A list of keys that correspond to other services that this service depends on.
     */
    public function __construct(array $dependencies)
    {
        $this->dependencies = $dependencies;
    }
    /**
     * Retrieves the keys of dependent services.
     *
     * @return string[] A list of strings each representing the key of a service.
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }
    /**
     * Creates a copy of this service with different dependency keys.
     *
     * @param array $dependencies The new service dependency keys.
     *
     * @return static The newly created service instance.
     */
    public function withDependencies(array $dependencies)
    {
        $instance = clone $this;
        $instance->dependencies = $dependencies;
        return $instance;
    }
    /**
     * Creates a value for this service using a given container for dependency resolution.
     *
     * @param ContainerInterface $c The container to use to resolve dependencies.
     *
     * @return mixed The created service value.
     */
    abstract public function __invoke(ContainerInterface $c);
}
