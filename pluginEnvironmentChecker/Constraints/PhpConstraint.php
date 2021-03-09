<?php

namespace Inpsyde\EnvironmentChecker\Constraints;

class PhpConstraint extends AbstractVersionConstraint
{

	/**
	 * PhpAbstractVersionConstraint constructor.
	 *
	 * @param $requiredVersion
	 */
	public function __construct($requiredVersion)
	{
		parent::__construct($requiredVersion);
		$this->error = 'Php version incompatibility';
	}

	/**
	 * @inheritDoc
	 */
	public function check()
	{
		$this->message = 'PHP version has to be '
			. $this->requiredVersion
			. ' or higher. Please update your PHP version';
		return $this->checkVersion(
			PHP_VERSION
		);
	}
}
