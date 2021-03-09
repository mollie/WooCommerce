<?php

namespace Inpsyde\EnvironmentChecker;

use Inpsyde\EnvironmentChecker\Constraints\AbstractVersionConstraint;
use Inpsyde\EnvironmentChecker\Constraints\ConstraintsCollection;

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
