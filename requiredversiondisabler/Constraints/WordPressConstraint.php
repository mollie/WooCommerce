<?php

namespace RequiredVersionDisabler\Constraints;

use RequiredVersionDisabler\Notice\AdminNotice;

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
            $message
                = '%1$s%3$s%2$s: Plugin disabled. WordPress version has to be '
                . $this->requiredVersion
                . ' or higher. Please update your WordPress version';
            $this->showNotice($message);
            return false;
        }

        return true;
    }

    /**
     * Show error notice
     *
     * @param $message
     */
    protected function showNotice($message)
    {
        $message = sprintf(
            esc_html__(
                $message,
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            $this->pluginName
        );
        $notice = new AdminNotice();
        $notice->addAdminNotice('error', $message);
    }
}
