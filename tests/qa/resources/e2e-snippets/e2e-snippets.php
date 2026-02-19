<?php
/**
 * Plugin Name:	E2E Snippets
 *
 * Description:	Snippets used in E2E tests.
 */

/**
 * Disable the "Disable Welcome Messages" in the Gutenberg Editor.
 */
add_filter(
	'block_editor_settings_all',
	function (array $settings): array {
		$settings['welcomeGuide'] = false;
		return $settings;
	}
);

/**
 * Disable WooCommerce Setup Wizard
 */
delete_transient('_wc_activation_redirect');
add_filter('woocommerce_enable_setup_wizard', '__return_false');

/**
 * Disable nonce check
 */
add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
