<?php

namespace RequiredVersionDisabler\Constraints;

class WooCommerceConstraint extends Constraint
{
    const CLASS_NAME = 'WooCommerce';


    /**
     * WooCommerceConstraint constructor.
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
     * Check if the installation has the required WC version
     * show notice if not.
     *
     * @return bool|mixed
     */
    public function check()
    {
        $classExists = class_exists(self::CLASS_NAME);
        if (!$classExists) {
            $message
                = sprintf(
                esc_html__(
                    '%1$s%7$s%2$s The %3$sWooCommerce plugin%4$s must be active for it to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s',
                    'mollie-payments-for-woocommerce'
                ),
                '<strong>',
                '</strong>',
                '<a href="https://wordpress.org/plugins/woocommerce/">',
                '</a>',
                '<a href="' . esc_url(admin_url('plugins.php')) . '">',
                '</a>',
                $this->pluginName
            );
            $this->showNotice($message);
            return false;
        }

        $WCCurrentVersion = get_option('woocommerce_version');
        $isWooCommerceVersionCompatible = version_compare(
            $WCCurrentVersion,
            $this->requiredVersion,
            '>='
        );
        if (!$isWooCommerceVersionCompatible) {
            $message
                = sprintf(
                esc_html__(
                    '%1$s%7$s%2$s: Plugin disabled. The %3$sWooCommerce plugin%4$s has to be version '
                    . $this->requiredVersion
                    . ' or higher. Please %5$sinstall & activate WooCommerce &raquo;%6$s',
                    'mollie-payments-for-woocommerce'
                ),
                '<strong>',
                '</strong>',
                '<a href="https://wordpress.org/plugins/woocommerce/">',
                '</a>',
                '<a href="' . esc_url(admin_url('plugins.php')) . '">',
                '</a>',
                $this->pluginName
            );
            $this->showNotice($message);
            return false;
        }

        return true;
    }
}
