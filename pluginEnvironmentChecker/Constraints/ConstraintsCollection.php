<?php

namespace Inpsyde\EnvironmentChecker\Constraints;

class ConstraintsCollection
{
    /**
     * @var AbstractVersionConstraint[]
     */
    protected $constraints;

    public function __construct(AbstractVersionConstraint ...$constraints)
    {
        $this->constraints = $constraints;
    }

    /**
     * Returns the array of set constraints
     *
     * @return AbstractVersionConstraint[]
     */
    public function constraints()
    {
        return $this->constraints;
    }
}
