<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Container;

use Mollie\Inpsyde\Modularity\Package;
use Mollie\Psr\Container\ContainerExceptionInterface;
use Mollie\Psr\Container\ContainerInterface;
class PackageProxyContainer implements ContainerInterface
{
    private Package $package;
    private ?ContainerInterface $container = null;
    /**
     * @param Package $package
     */
    public function __construct(Package $package)
    {
        $this->package = $package;
    }
    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        $this->assertPackageBooted($id);
        return $this->container->get($id);
    }
    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->tryContainer() && $this->container->has($id);
    }
    /**
     * @return bool
     *
     * @phpstan-assert-if-true ContainerInterface $this->container
     * @phpstan-assert-if-false null $this->container
     */
    private function tryContainer(): bool
    {
        if ($this->container !== null) {
            return \true;
        }
        if ($this->package->hasContainer() || $this->package->hasReachedStatus(Package::STATUS_INITIALIZED)) {
            $this->container = $this->package->container();
        }
        return $this->container !== null;
    }
    /**
     * @param string $id
     * @return void
     *
     * @phpstan-assert ContainerInterface $this->container
     */
    private function assertPackageBooted(string $id): void
    {
        if ($this->tryContainer()) {
            return;
        }
        $name = $this->package->name();
        $status = $this->package->hasFailed() ? 'is errored' : 'is not ready yet';
        $error = "Error retrieving service {$id} because package {$name} {$status}.";
        throw new class(esc_html($error)) extends \Exception implements ContainerExceptionInterface
        {
        };
    }
}
