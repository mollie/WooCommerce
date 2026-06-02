<?php

declare (strict_types=1);
namespace Mollie;

/**
 * Plugin Name: ddev-wordpress-plugin-example
 * Plugin URI:  https://inpsyde.com
 * Description: {DESCRIPTION}
 * Version: 8.1.6+develop.e43213b
 * SHA: e43213b972a7405dd81296b579c83413d3833a80
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
