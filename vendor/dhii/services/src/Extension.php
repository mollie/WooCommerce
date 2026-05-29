<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services;

use Mollie\Psr\Container\ContainerInterface;
/**
 * An extension service.
 *
 * This implementation behaves very similarly to {@link Factory}, in that a given definition function will be invoked
 * and its result will be used as the service value. However, extension definition functions will receive an additional
 * first argument, which should hold the value of the service that is being extended or the value yielded by a previous
 * extension. Any resolved dependencies will be passed as arguments for the second parameter and onwards.
 *
 * Example usage:
 * ```
 * new Extension(['foo', 'bar'], function ($prev, $foo, $bar) {
 *      $prev['data'] = [$foo, bar];
 *
 *      return $prev;
 * });
 * ```
 *
 * @see Factory For a similar implementation that does not accept a previous service value.
 */
class Extension extends Service
{
    use ResolveKeysCapableTrait;
    /** @var callable */
    protected $definition;
    /**
     * @inheritDoc
     *
     * @param callable $definition The extension definition.
     */
    public function __construct(array $dependencies, callable $definition)
    {
        parent::__construct($dependencies);
        $this->definition = $definition;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $c, $prev = null)
    {
        $deps = $this->resolveKeys($c, $this->dependencies);
        array_unshift($deps, $prev);
        return ($this->definition)(...$deps);
    }
}
