<?php

namespace Mollie\Inpsyde\EnvironmentChecker\Constraints;

use Mollie\Inpsyde\EnvironmentChecker\Exception\ConstraintFailedExceptionInterface;
use RuntimeException;
/**
 * Interface ConstraintInterface
 *
 * @package RequiredVersionDisabler
 */
interface ConstraintInterface
{
    /**
     * Validates a value.
     *
     * @throws RuntimeException                                    If problem validating.
     * @throws ConstraintFailedExceptionInterface                  If validation failed. Must extend {@see RuntimeException}.
     */
    public function check();
}
