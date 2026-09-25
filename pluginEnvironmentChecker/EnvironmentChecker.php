<?php

namespace Mollie\Inpsyde\EnvironmentChecker;

use Mollie\Inpsyde\EnvironmentChecker\Constraints\AbstractVersionConstraint;
use Mollie\Inpsyde\EnvironmentChecker\Constraints\ConstraintInterface;
use Mollie\Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;
use Mollie\Inpsyde\EnvironmentChecker\Exception\ConstraintFailedExceptionInterface;
class EnvironmentChecker implements ConstraintInterface
{
    protected $constraintsArray;
    /**
     * @var array<string> List of the error messages if environment is not ok.
     */
    protected $errors;
    /**
     * __construct function.
     *
     * @access public
     *
     * @param AbstractVersionConstraint[] $constraintsArray
     */
    function __construct(array $constraintsArray)
    {
        $this->constraintsArray = $constraintsArray;
        $this->errors = [];
    }
    public function check()
    {
        foreach ($this->constraintsArray as $constraint) {
            assert($constraint instanceof ConstraintInterface);
            try {
                $constraint->check();
            } catch (ConstraintFailedExceptionInterface $e) {
                $this->errors[] = $e;
            }
        }
        $errCount = count($this->errors);
        if ($errCount) {
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConstraintFailedException($this, 'General Checker', $this->errors, $this->esc_html__('Validation failed with %1$d errors', [$errCount]));
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
    /**
     * Translates a string, interpolating params.
     *
     * @param string $string The string to translate. Can be a {@see sprintf()} style format.
     * @param array $params The param values to interpolate into the string.
     * @return string The translated string with params interpolated.
     */
    protected function esc_html__($string, $params = [])
    {
        $interpolated = vsprintf($string, $params);
        return $interpolated && esc_html($interpolated);
    }
}
