<?php

namespace Inpsyde\EnvironmentChecker\Exception;

use Inpsyde\EnvironmentChecker\Constraints\ConstraintInterface;
use RuntimeException;

class ConstraintFailedException extends RuntimeException implements
	ConstraintFailedExceptionInterface
{
	/** @var ConstraintInterface */
	protected $validator;
	/** @var mixed */
	protected $subject;

	protected $errors;

	/**
	 * @param ConstraintInterface $validator  The failed validator.
	 * @param mixed               $subject    The subject that was being validated.
	 * @param array|string        $errors     An array of errors or the error string itself,
	 *                                        that represent errors.
	 * @param string              $message    The error message.
	 * @param int                 $code       The error code.
	 * @param array               $previous   The inner error.
	 */
	public function __construct(
		ConstraintInterface $validator,
		$subject,
		$errors,
		$message = '',
		$code = 0,
		$previous = null
	) {
		parent::__construct($message, $code, $previous);
		$this->validator = $validator;
		$this->subject = $subject;
		$this->errors = $errors;
	}

	/**
	 * @inheritDoc
	 */
	public function getValidator()
	{
		return $this->validator;
	}

	/**
	 * @inheritDoc
	 */
	public function getValidationErrors()
	{
		return $this->errors;
	}

	/**
	 * @inheritDoc
	 */
	public function getValidationSubject()
	{
		return $this->subject;
	}
}