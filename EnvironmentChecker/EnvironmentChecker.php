<?php

namespace EnvironmentChecker;

use EnvironmentChecker\Constraints\Constraint;

class EnvironmentChecker {

    private $constraintsArray;

    /**
     * __construct function.
     *
     * @access public
     *
     * @param Constraint[] $constraintsArray
     */
	function __construct(array $constraintsArray) {

	   $this->constraintsArray = $constraintsArray;
	}

    /**
     * Check if this installation meets all the requirements
     *
     * @return bool True if there are no restrictions or all the restrictions are true
     */
    public function isCompatible()
    {
        foreach ($this->constraintsArray as $constraint){
           $result = $constraint->check();
           if(!$result){
               return false;
           }
        }

        return true;
    }
}
