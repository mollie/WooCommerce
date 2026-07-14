<?php

declare (strict_types=1);
namespace Mollie\Dhii\Services\Factories;

use Mollie\Dhii\Services\Service;
use Mollie\Psr\Container\ContainerInterface;
/**
 * A service that always returns the same value.
 *
 * Value services will always ignore the container argument and return the same pre-configured value when invoked.
 */
class Value extends Service
{
    /** @var mixed */
    protected $value;
    /**
     * Constructor.
     *
     * @param mixed $value The value.
     */
    public function __construct($value)
    {
        parent::__construct([]);
        $this->value = $value;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $c)
    {
        return $this->value;
    }
}
