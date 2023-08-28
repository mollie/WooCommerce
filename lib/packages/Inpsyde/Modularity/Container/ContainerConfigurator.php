<?php
declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Container;

use Mollie\WooCommerce\Vendor\Psr\Container\ContainerExceptionInterface;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;

class ContainerConfigurator
{
    /**
     * @var array<string, callable(ContainerInterface $container):mixed>
     */
    private $services = [];

    /**
     * @var array<string, bool>
     */
    private $factoryIds = [];

    /**
     * @var array<string, array<callable(mixed $service, ContainerInterface $container):mixed>>
     */
    private $extensions = [];

    /**
     * @var ContainerInterface[]
     */
    private $containers = [];

    /**
     * @var null|ContainerInterface
     */
    private $compiledContainer;

    /**
     * ContainerConfigurator constructor.
     *
     * @param ContainerInterface[] $containers
     */
    public function __construct(array $containers = [])
    {
        array_map([$this, 'addContainer'], $containers);
    }

    /**
     * Allowing to add child containers.
     *
     * @param ContainerInterface $container
     */
    public function addContainer(ContainerInterface $container): void
    {
        $this->containers[] = $container;
    }

    /**
     * @param string $id
     * @param callable(ContainerInterface $container):mixed $factory
     */
    public function addFactory(string $id, callable $factory): void
    {
        $this->addService($id, $factory);
        // We're using a hash table to detect later
        // via isset() if a Service as a Factory.
        $this->factoryIds[$id] = true;
    }

    /**
     * @param string $id
     * @param callable(ContainerInterface $container):mixed $service
     *
     * @return void
     */
    public function addService(string $id, callable $service): void
    {
        if ($this->hasService($id)) {
            /*
             * We are being intentionally permissive here,
             * allowing a simple workflow for *intentional* overrides
             * while accepting the (small?) risk of *accidental* overrides
             * that could be hard to notice and debug.
             */

            /*
             * Clear a factory flag in case it was a factory.
             * If needs be, it will get re-added after this function completes.
             */
            unset($this->factoryIds[$id]);
        }

        $this->services[$id] = $service;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasService(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return true;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $id
     * @param callable(mixed $service, ContainerInterface $container):mixed $extender
     *
     * @return void
     */
    public function addExtension(string $id, callable $extender): void
    {
        if (!isset($this->extensions[$id])) {
            $this->extensions[$id] = [];
        }

        $this->extensions[$id][] = $extender;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasExtension(string $id): bool
    {
        return isset($this->extensions[$id]);
    }

    /**
     * Returns a read only version of this Container.
     *
     * @return ContainerInterface
     */
    public function createReadOnlyContainer(): ContainerInterface
    {
        if (!$this->compiledContainer) {
            $this->compiledContainer = new ReadOnlyContainer(
                $this->services,
                $this->factoryIds,
                $this->extensions,
                $this->containers
            );
        }

        return $this->compiledContainer;
    }
}
