<?php

namespace Inpsyde\EnvironmentChecker\Constraints;

use Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;

class ExtensionConstraint extends AbstractVersionConstraint
{

	/**
	 * PhpAbstractVersionConstraint constructor.
	 *
	 * @param string $requiredVersion
	 */
	public function __construct($requiredVersion)
	{
		parent::__construct($requiredVersion);
		$this->error = esc_html('Required Extension not loaded');
	}

	/**
	 * @inheritDoc
	 */
	public function check()
	{
		$this->message = $this->requiredVersion
			. ' extension is required. Enable it in your server or ask your webhoster to enable it for you.';
        $this->message = esc_html($this->message);
		if (function_exists('extension_loaded')
			&& !extension_loaded(
				$this->requiredVersion
			)
		) {
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConstraintFailedException(
				$this,
				$this->requiredVersion,
				[$this->error],
				$this->message
			);
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
		return true;
	}
}
