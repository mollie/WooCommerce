<?php
/**
 * Plugin Name: MPM - Mollie Payment Module
 * Plugin URI: https://github.com/Mollie/WooCommerce/releases
 * Version: 1.1.1
 * Description: Integration of the Mollie API for WooCommerce
 * Author: Mollie
 * Author URI: https://www.mollie.nl
 * Text Domain: MPM
 * Domain Path: MPM
 * License: http://www.opensource.org/licenses/bsd-license.php  Berkeley Software Distribution License (BSD-License 2)
 */
/**
 * Copyright (c) 2014, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */

// Register
add_action('plugins_loaded', 'mpm_init');
add_action('wp_ajax_nopriv_mollie_webhook', 'mpm_webhook');
add_action('wp_ajax_mollie_webhook', 'mpm_webhook');
add_action('wp_ajax_mollie_refund', 'mpm_refund');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mpm_action_links');

register_uninstall_hook(__FILE__, 'mpm_uninstall');

// Load required components
$wp_rewrite = new WP_Rewrite();

function mpm_init()
{
	// Define language folder
	load_plugin_textdomain('MPM', FALSE, plugin_basename(dirname(__FILE__)) . '/languages');

	// Define includes
	require_once(dirname(__FILE__) . '/lib/src/Mollie/API/Autoloader.php');
	if (class_exists('WC_Payment_Gateway', FALSE))
	{
		require_once(dirname(__FILE__) . '/mpm_gateway.php');
	}
	if (class_exists('WC_Settings_API', FALSE))
	{
		require_once(dirname(__FILE__) . '/mpm_settings.php');
		require_once(dirname(__FILE__) . '/mpm_return.php');

		// Instantiate
		new MPM_Settings();
	}
}

function mpm_action_links($links)
{
	return array_merge(
		array(
			'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mpm_settings') . '">' . __( 'Settings', 'MPM' ) . '</a>'
		),
		$links
	);
}

/**
 * Receive payment status
 * @return void
 */
function mpm_webhook()
{
	if (isset($_GET['testByMollie']))
	{
		update_option('woocommerce_mpm_webhook_tested', 'yes');
	}
	elseif (isset($_REQUEST['id']))
	{
		global $mpm;
		$payment	= $mpm->api->payments->get($_REQUEST['id']);
		$order_id	= $payment->metadata->order_id;
		$order		= new WC_Order( $order_id );

		if ($payment->isPaid())
		{
			$order->payment_complete();
		}
		elseif ($payment->isOpen() === FALSE)
		{
			if ($payment->status === Mollie_API_Object_Payment::STATUS_CANCELLED)
			{
				$order->cancel_order();
			}
			else
			{
				unset(WC()->session->order_awaiting_payment);
				$order->update_status('failed');
				$order->decrease_coupon_usage_counts();
			}
		}
		update_option('woocommerce_mpm_webhook_tested', 'yes');
	}
	die('OK');
}

/**
 * Refund a transaction
 * @return void
 */
function mpm_refund()
{
	$error = 'Incomplete request';
	$order_id = null;
	$msg = get_option('woocommerce_mpm_message_start', 11);
	if (isset($_REQUEST['id'], $_REQUEST['key']))
	{
		global $mpm;
		require_once(dirname(__FILE__) . '/mpm_settings.php');
		new MPM_Settings();

		$order_id = intval($_REQUEST['id']);
		$key = $_REQUEST['key'];

		if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mollie_refund') || $order = $mpm->order_get($order_id, $key) === FALSE)
		{
			// invalid nonce or order key
			$error = 'Invalid input';
		}
		else
		{
			// valid refund attempt
			try
			{
				$transaction_id = get_post_meta($order_id, '_mollie_transaction_id', TRUE);
				$transaction = $mpm->api->payments->get($transaction_id);
				// Actual refund
				$mpm->api->payments->refund($transaction);
				$order->update_status('refunded');
				$error = '';
			}
			catch (Exception $e)
			{
				$error = $e->getMessage();
			}
		}
	}
	if (!empty($error))
	{
		$error = '&error=' . urlencode($error);
		$msg++;
	}

	wp_redirect(admin_url('post.php') . '?post=' . $order_id . '&action=edit&message=' . $msg . $error);
}

/**
 * Uninstall the module
 */
function mpm_uninstall()
{
	// Remove options
	delete_option('woocommerce_mpm_settings');
	delete_option('woocommerce_mpm_webhook_tested');

	// Remove as gateway
	$gateways = (array) get_option('woocommerce_gateway_order');
	if (array_key_exists('mpm', $gateways));
	{
		unset($gateways['mpm']);
	}
	update_option('woocommerce_gateway_order', $gateways);

	// Remove as uninstallable
	$plugins = (array) get_option('uninstall_plugins');
	if (array_key_exists('MPM/mpm.php', $plugins));
	{
		unset($plugins['MPM/mpm.php']);
	}
	update_option('uninstall_plugins', $plugins);
}
