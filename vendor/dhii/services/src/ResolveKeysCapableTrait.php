<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services;

use Generator;
use Mollie\Psr\Container\ContainerInterface;
trait ResolveKeysCapableTrait
{
    /**
     * Resolves a set of service keys using a given container.
     *
     * @param ContainerInterface $c    The container to use for service resolution.
     * @param array<string>      $keys The services keys to resolve.
     *
     * @return array<int,mixed> A list containing the resolved service values, in the same order as in $keys.
     */
    protected function resolveKeys(ContainerInterface $c, array $keys): array
    {
        return array_values($this->resolveDeps($c, $keys));
    }
    /**
     * Resolves a set of service keys using a given container.
     *
     * @param ContainerInterface $c    The container to use for service resolution.
     * @param array<string>      $keys The services keys to resolve.
     *
     * @return array<string,mixed> A map of specified service keys to their resolved values,
     *                             in the same order as in $keys.
     */
    protected function resolveDeps(ContainerInterface $c, array $keys): array
    {
        return iterator_to_array((function () use ($c, $keys): Generator {
            foreach ($keys as $key) {
                yield $key => $c->get($key);
            }
        })());
    }
}
