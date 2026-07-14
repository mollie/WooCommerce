<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services\Factories;

use Mollie\Dhii\Services\Service;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Container\NotFoundExceptionInterface;
/**
 * A service implementation for aliasing another service, with an optional fallback mechanism.
 *
 * Alias services attempt to resolve to another service in a container, referenced by key. If that service is not found,
 * an optional default definition will be invoked. This definition will be treated as a regular definition, meaning it
 * will be invoked with just the container as an argument. This allows for both regular functions, as well as
 * {@link Service} implementations to be used as the default definition.
 *
 * Example usage:
 * ```
 * new Alias('original_service', function (ContainerInterface $c) {
 *      return 'some_default';
 * });
 *
 * new Alias('original_service', new Factory(['foo', 'bar'], function ($foo, $bar) {
 *      return $foo + $bar;
 * });
 * ```
 *
 */
class Alias extends Service
{
    /** @var string */
    protected $key;
    /** @var callable|null */
    protected $default;
    /**
     * Constructor.
     *
     * @param string        $key     The key of the original service to be aliased.
     * @param callable|null $default Optional default definition to use if the original service is not found. If not
     *                               given or null, the service will attempt to fetch the original service from the
     *                               container anyway, which will result in a thrown {@link NotFoundExceptionInterface}.
     */
    public function __construct(string $key, callable $default = null)
    {
        parent::__construct([]);
        $this->key = $key;
        $this->default = $default;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $c)
    {
        if (!$c->has($this->key) && $this->default !== null) {
            return ($this->default)($c);
        }
        return $c->get($this->key);
    }
}
