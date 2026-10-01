<?php

declare (strict_types=1);
namespace Mollie;

/**
 * Plugin Name: ddev-wordpress-plugin-example
 * Plugin URI:  https://inpsyde.com
 * Description: {DESCRIPTION}
 * Version: 8.1.10+piwoo-954-update-the-bundled-apple-pay-domain-verification-file.d261d55
 * SHA: d261d5590ed97db93fe26d7f22d24bd91f57a0d0
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
