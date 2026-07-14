<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services\Factories;

use Mollie\Dhii\Services\Service;
use Mollie\Psr\Container\ContainerInterface;
/**
 * A service that references a global variable.
 *
 * Example usage:
 *
 * ```
 * global $var;
 * $var = 5;
 *
 * $service = new GlobalVarService('var')
 *
 * $service($c) // 5
 * ```
 *
 */
class GlobalVar extends Service
{
    /** @var string */
    protected $name;
    /**
     * Constructor.
     *
     * @param string $name The name of the global variable.
     */
    public function __construct(string $name)
    {
        parent::__construct([]);
        $this->name = $name;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $c)
    {
        global ${$this->name};
        return ${$this->name};
    }
}
