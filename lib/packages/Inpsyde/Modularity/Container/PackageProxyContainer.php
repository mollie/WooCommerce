<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Container;

use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Package;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerExceptionInterface;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;

class PackageProxyContainer implements ContainerInterface
{
    /**
     * @var Package
     */
    private $package;

    /**
     * @var ContainerInterface|null
     */
    private $container;

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
     *
     * @throws \Exception
     */
    public function get($id)
    {
        assert(is_string($id));
        $this->assertPackageBooted($id);

        return $this->container->get($id);
    }

    /**
     * @param string $id
     * @return bool
     *
     * @throws \Exception
     */
    public function has($id)
    {
        assert(is_string($id));

        return $this->tryContainer() && $this->container->has($id);
    }

    /**
     * @return bool
     *
     * @throws \Exception
     * @psalm-assert-if-true ContainerInterface $this->container
     */
    private function tryContainer(): bool
    {
        if ($this->container) {
            return true;
        }

        if ($this->package->statusIs(Package::STATUS_BOOTED)) {
            $this->container = $this->package->container();
        }

        return (bool)$this->container;
    }

    /**
     * @param string $id
     * @return void
     *
     * @throws \Exception
     *
     * @psalm-assert ContainerInterface $this->container
     */
    private function assertPackageBooted(string $id): void
    {
        if ($this->tryContainer()) {
            return;
        }

        $name = $this->package->name();
        $status = $this->package->statusIs(Package::STATUS_FAILED)
            ? 'failed booting'
            : 'is not booted yet';

        throw new class ("Error retrieving service {$id} because package {$name} {$status}.")
            extends \Exception
            implements ContainerExceptionInterface {
        };
    }
}
