<?php

declare (strict_types=1);
namespace Mollie;

/**
 * Plugin Name: ddev-wordpress-plugin-example
 * Plugin URI:  https://inpsyde.com
 * Description: {DESCRIPTION}
 * Version: 8.1.11+piwoo-944-express-as-payment-method.48efaa0
 * SHA: 48efaa047aa7575bd6e0b1129f0a2601e2b7a440
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 4.3
 * WC tested up to: 5.5
 * Author:      Inpsyde
 * Author URI:  https://inpsyde.com
 * License:     GPL-2.0
 * Text Domain: ddev-wordpress-plugin-example
 * Domain Path: /languages
 */
\add_action('rest_api_init', function () {
    \register_rest_route('inpsyde', 'example', ['method' => 'GET', 'callback' => function () {
        return ['hello' => \__('world', 'ddev-wordpress-plugin-example')];
    }, 'permission_callback' => '__return_true']);
});
