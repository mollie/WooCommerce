<?php


namespace RequiredVersionDisabler\Constraints;


class ConstraintsCollection
{
    /**
     * @var Constraint[]
     */
    private $constraints;

    public function __construct(Constraint ...$constraints)
    {
        $this->constraints = $constraints;
    }

    /**
     * Returns the array of set constraints
     *
     * @return Constraint[]
     */
    public function constraints()
    {
        return $this->constraints;
    }
}
