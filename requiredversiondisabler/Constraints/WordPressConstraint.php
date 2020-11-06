<?php

namespace RequiredVersionDisabler\Constraints;

class WordPressConstraint extends Constraint
{

    /**
     * WordPressConstraint constructor.
     *
     * @param string $version
     * @param string $pluginName
     */
    public function __construct($version, $pluginName)
    {
        $this->requiredVersion = $version;
        $this->pluginName = $pluginName;
    }

    /**
     * Check if the installation has the required WP version
     * show notice if not.
     *
     * @return bool|mixed
     */
    public function check()
    {
        $WPCurrentVersion = get_bloginfo('version');

        $isWordPressVersionCompatible = version_compare(
            $WPCurrentVersion,
            $this->requiredVersion,
            '>='
        );

        if (!$isWordPressVersionCompatible) {
            $message = sprintf(
                esc_html__(
                    '%1$s%3$s%2$s: Plugin disabled. WordPress version has to be '
                    . $this->requiredVersion
                    . ' or higher. Please update your WordPress version',
                    'mollie-payments-for-woocommerce'
                ),
                '<strong>',
                '</strong>',
                $this->pluginName
            );
            $this->showNotice($message);
            return false;
        }

        return true;
    }

}
