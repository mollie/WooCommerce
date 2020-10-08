<?php

namespace RequiredVersionDisabler;

use RequiredVersionDisabler\Notice\AdminNotice;

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

    public function check()
    {
        $WCCurrentVersion = get_option('woocommerce_version');
        $isWooCommerceVersionCompatible = version_compare(
            $WCCurrentVersion,
            $this->requiredVersion,
            '>='
        );

        $this->maybeShowNotice();

        return class_exists(self::CLASS_NAME) && $isWooCommerceVersionCompatible;
    }

    protected function maybeShowNotice()
    {
        if(!class_exists(self::CLASS_NAME)){
            $notice = new AdminNotice();
            $message = sprintf(
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
            $notice->addAdminNotice('error', $message);
        }
    }
}
