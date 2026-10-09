<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Container;

use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Container\NotFoundExceptionInterface;
/**
 * @phpstan-import-type Service from \Inpsyde\Modularity\Module\ServiceModule
 * @phpstan-import-type ExtendingService from \Inpsyde\Modularity\Module\ExtendingModule
 */
class ReadOnlyContainer implements ContainerInterface
{
    /** @var array<string, Service> */
    private array $services;
    /** @var array<string, bool> */
    private array $factoryIds;
    private ServiceExtensions $extensions;
    /** @var ContainerInterface[] */
    private array $containers;
    /** @var array<string, mixed> */
    private array $resolvedServices = [];
    /**
     * @param array<string, Service> $services
     * @param array<string, bool> $factoryIds
     * @param ServiceExtensions|array<string, ExtendingService> $extensions
     * @param ContainerInterface[] $containers
     */
    public function __construct(array $services, array $factoryIds, $extensions, array $containers)
    {
        $this->services = $services;
        $this->factoryIds = $factoryIds;
        $this->extensions = $this->configureServiceExtensions($extensions);
        $this->containers = $containers;
    }
    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->resolvedServices)) {
            return $this->resolvedServices[$id];
        }
        if (array_key_exists($id, $this->services)) {
            $service = $this->services[$id]($this);
            $resolved = $this->extensions->resolve($service, $id, $this);
            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $resolved;
                unset($this->services[$id]);
            }
            return $resolved;
        }
        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                $service = $container->get($id);
                return $this->extensions->resolve($service, $id, $this);
            }
        }
        $error = "Service with ID {$id} not found.";
        throw new class(esc_html($error)) extends \Exception implements NotFoundExceptionInterface
        {
        };
    }
    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return \true;
        }
        if (array_key_exists($id, $this->resolvedServices)) {
            return \true;
        }
        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Support extensions as array or ServiceExtensions instance for backward compatibility.
     *
     * With PHP 8+ we could use an actual union type, but when we bump to PHP 8 as min supported
     * version, we will probably bump major version as well, so we can just get rid of support
     * for array.
     *
     * @param mixed $extensions
     * @return ServiceExtensions
     */
    private function configureServiceExtensions($extensions): ServiceExtensions
    {
        if ($extensions instanceof ServiceExtensions) {
            return $extensions;
        }
        if (!is_array($extensions)) {
            $type = is_object($extensions) ? get_class($extensions) : gettype($extensions);
            throw new \TypeError(sprintf('%s::%s(): Argument #3 ($extensions) must be of type %s|array, %s given', __CLASS__, '__construct', ServiceExtensions::class, esc_html($type)));
        }
        $servicesExtensions = new ServiceExtensions();
        foreach ($extensions as $id => $callback) {
            /**
             * @var string $id
             * @var ExtendingService $callback
             */
            $servicesExtensions->add($id, $callback);
        }
        return $servicesExtensions;
    }
}
