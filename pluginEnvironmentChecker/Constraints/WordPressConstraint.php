<?php

namespace Inpsyde\EnvironmentChecker\Constraints;

class WordPressConstraint extends AbstractVersionConstraint
{
	/**
	 * WordPressAbstractVersionConstraint constructor.
	 *
	 * @param $requiredVersion
	 */
	public function __construct($requiredVersion)
	{
		parent::__construct($requiredVersion);
		$this->error = esc_html('Wordpress version incompatibility');
	}

    /**
     * @inheritDoc
     */
    public function check()
    {
	    $WPCurrentVersion = get_bloginfo('version');
	    $this->message = 'WordPress version has to be '
		    . $this->requiredVersion
		    . ' or higher. Please update your WordPress version';
        $this->message = esc_html($this->message);

	    return $this->checkVersion(
		    $WPCurrentVersion
	    );
    }
}
