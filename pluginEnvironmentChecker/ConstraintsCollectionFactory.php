<?php

namespace Mollie\Inpsyde\EnvironmentChecker;

use Mollie\Inpsyde\EnvironmentChecker\Constraints\AbstractVersionConstraint;
use Mollie\Inpsyde\EnvironmentChecker\Constraints\ConstraintsCollection;
class ConstraintsCollectionFactory
{
    /**
     * Creates a Constraints Collection
     *
     * @param AbstractVersionConstraint ...$constraints
     *
     * @return ConstraintsCollection
     */
    public function create(AbstractVersionConstraint ...$constraints)
    {
        return new ConstraintsCollection(...$constraints);
    }
}
