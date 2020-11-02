<?php

namespace RequiredVersionDisabler\Constraints;

use RequiredVersionDisabler\Notice\AdminNotice;

class PhpConstraint extends Constraint
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
     * Check if the installation has the required PHP version
     * show notice if not.
     *
     * @return bool|mixed
     */
    public function check()
    {
        $isPhpVersionCompatible = version_compare(
            PHP_VERSION,
            $this->requiredVersion,
            '>='
        );
        if (!$isPhpVersionCompatible) {
            $message
                = '%1$s%3$s%2$s: Plugin disabled. PHP version has to be '
                . $this->requiredVersion
                . ' or higher. Please update your PHP version';
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
