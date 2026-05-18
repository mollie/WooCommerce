<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services\Factories;

use Mollie\Dhii\Services\ResolveKeysCapableTrait;
use Mollie\Dhii\Services\Service;
use Mollie\Psr\Container\ContainerInterface;
/**
 * A function service.
 *
 * Services of this type will resolve to a function.
 * Example usage:
 *
 * ```
 * $service = new FuncService(['foo', 'bar'], function ($foo, $bar) {
 *      return $foo + $bar;
 * });
 *
 * $fn = $service($c);
 * $fn();
 * ```
 *
 * The function may accept additional call-time arguments. These arguments will be passed _before_ the resolved
 * dependencies:
 *
 * ```
 * $service = new FuncService(['foo', 'bar'], function ($arg1, $arg2, $foo, $bar) {
 *      return ($arg1 + $arg2) * ($foo + $bar);
 * });
 *
 * $fn = $service($c);
 * $fn($arg1, $arg2);
 * ```
 *
 */
class FuncService extends Service
{
    use ResolveKeysCapableTrait;
    /** @var callable */
    protected $function;
    /**
     * @inheritDoc
     *
     * @param callable $function The function to return when the service is created.
     */
    public function __construct(array $dependencies, callable $function)
    {
        parent::__construct($dependencies);
        $this->function = $function;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $c)
    {
        $deps = $this->resolveKeys($c, $this->dependencies);
        /**
         * @psalm-suppress MissingClosureReturnType Cannot declare mixed until PHP 8
         * @psalm-suppress MissingClosureParamType Cannot declare mixed until PHP 8
         */
        return function (...$args) use ($deps) {
            return ($this->function)(...$args, ...$deps);
        };
    }
}
