<?php

declare (strict_types=1);
namespace Mollie\WooCommerce;

use Mollie\Inpsyde\Modularity\Package;
use Mollie\Inpsyde\Modularity\Properties\PluginProperties;
use Mollie\WooCommerce\Uninstall\CleanDb;
use Mollie\WooCommerce\Uninstall\UninstallModule;
use Throwable;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Direct access not allowed.');
}
(static function (): void {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        include_once __DIR__ . '/vendor/autoload.php';
    }
    try {
        $properties = PluginProperties::new(__FILE__);
        $bootstrap = Package::new($properties);
        $bootstrap->addModule(new UninstallModule());
        $bootstrap->boot();
        $shouldClean = get_option('mollie-payments-for-woocommerce_removeOptionsAndTransients') === 'yes';
        if ($shouldClean) {
            $cleaner = $bootstrap->container()->get(CleanDb::class);
            $cleaner->cleanAll();
        }
    } catch (Throwable $throwable) {
        $message = sprintf('<strong>Error:</strong> %s <br><pre>%s</pre>', $throwable->getMessage(), $throwable->getTraceAsString());
        add_action('all_admin_notices', static function () use ($message) {
            $class = 'notice notice-error';
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
    }
})();
