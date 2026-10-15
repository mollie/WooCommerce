<?php

namespace Mollie\Inpsyde\EnvironmentChecker\Exception;

use Exception;
interface ConstraintFailedExceptionInterface
{
    /**
     * Retrieves validation errors that are associated with this instance.
     *
     * @return array A list of errors.
     *                           Each error is something that can be treated as a string, and represents
     *                           a description of why a validation subject is invalid.
     *
     * @throws Exception If problem retrieving.
     */
    public function getValidationErrors();
    /**
     * Returns the subject, the validation for which failed.
     *
     * @return mixed The subject that was being validated.
     *
     * @throws Exception If problem retrieving.
     */
    public function getValidationSubject();
}
