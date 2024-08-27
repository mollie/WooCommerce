<?php

namespace Inpsyde\EnvironmentChecker\Constraints;

use Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;

abstract class AbstractVersionConstraint implements ConstraintInterface
{
	/**
	 * @var string Version against we need to check
	 */
	protected $requiredVersion;

	/**
	 * @var mixed|null className of the plugin we need to check against
	 */
	protected $requiredPluginName;

	/**
	 * @var string
	 */
	protected $message;

	/**
	 * @var string
	 */
	protected $error;


	/**
	 * PhpAbstractVersionConstraint constructor.
	 *
	 * @param      $requiredVersion
	 * @param null $requiredPluginName Used to pass the name of the plugin to check
	 */
	public function __construct($requiredVersion, $requiredPluginName = null)
	{
		$this->requiredVersion = $requiredVersion;
		$this->requiredPluginName = esc_html($requiredPluginName);
		$this->error = '';
		$this->message = '';
	}

	/**
	 * Check if $actualVersion less then $requiredVersion.
	 *
	 * @param string $actualVersion
	 *
	 * @return bool
	 * @throws ConstraintFailedException
	 */
	protected function checkVersion($actualVersion)
	{
		$result = version_compare(
			$actualVersion,
			$this->requiredVersion,
			'>='
		);

		if ($result) {
			return $result;
		}
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new ConstraintFailedException(
			$this,
			$actualVersion,
			[$this->error],
			esc_html($this->message)
		);
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
