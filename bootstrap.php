<?php

declare (strict_types=1);
namespace Mollie\WooCommerce;

use Mollie\Inpsyde\Modularity\Package;
use Mollie\Inpsyde\Modularity\Properties\PluginProperties;
use Mollie\Psr\Container\ContainerInterface;
use Throwable;
/**
 * Bootstrap function to initialize the plugin.
 *
 * @param string $root_dir The main plugin file path
 * @param array $additional_modules Additional modules to load
 * @return callable A function that returns the container
 */
return function (string $root_dir, array $additional_modules = []): ContainerInterface {
    try {
        if (!function_exists('Mollie\mollieWooCommerceIsCheckoutContext')) {
            require_once __DIR__ . '/inc/functions.php';
        }
        if (!function_exists('Mollie\WooCommerce\mollie_wc_plugin_autoload') || !mollie_wc_plugin_autoload()) {
            throw new \RuntimeException('Autoloader could not be initialized.');
        }
        $checker = new \Mollie\WooCommerce\Activation\ConstraintsChecker();
        $meetRequirements = $checker->handleActivation();
        if (!$meetRequirements) {
            $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
            if ($nextScheduledTime) {
                wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
            }
            throw new \RuntimeException('Plugin requirements not met.');
        }
        // Initialize plugin.
        $properties = PluginProperties::new(\M4W_FILE);
        $package = Package::new($properties);
        $modules = (require $root_dir . '/inc/modules.php')();
        $modules = array_merge($modules, $additional_modules);
        $modules = apply_filters('mollie_wc_plugin_modules', $modules);
        foreach ($modules as $module) {
            $package->addModule($module);
        }
        $package->boot();
        return $package->container();
    } catch (Throwable $throwable) {
        if (function_exists('Mollie\WooCommerce\handleException')) {
            handleException($throwable);
        }
        throw $throwable;
    }
};
