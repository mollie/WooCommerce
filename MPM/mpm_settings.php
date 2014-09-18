<?php
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
// All settings are managed by the MPM class (they are payment_method-independent)
class MPM_Settings extends WC_Settings_API
{
	// Keep track of payment methods
	public $count = 0;

	/** @var $api Mollie_API_Client|null */
	protected $api = null;

	/**
	 * @var array
	 */
	public $supports = array();

	/** @var $return MPM_return|null */
	public $return = null;

	public $plugin_version = '1.2.5';
	public $update_url = 'https://github.com/mollie/WooCommerce';

	public function __construct()
	{
		// Enable payment methods to keep track of the settings object:
		global $mpm;
		if (!isset($mpm))
		{
			$mpm = $this;
		}
		$first = ($mpm === $this);

		// Settings
		$this->method_title = 'Mollie Payment Module';
		$this->id = 'mpm';
		$this->init_form_fields();
		$this->init_settings();
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

		// Things to do in controller mode only:
		if ($first)
		{
			// Get return handler
			$this->return = new MPM_return();

			// Filters & Actions
			add_action('woocommerce_admin_order_data_after_order_details', array(&$this, 'show_refund_button')); // adds a refund button to the admin order view
			add_filter('woocommerce_payment_gateways', array(&$this, 'gateways_add_dynamic')); // this includes the settings page
			add_filter('woocommerce_available_payment_gateways', array(&$this, 'gateways_add_static')); // this does not include the settings page
			add_filter('post_updated_messages', array(&$this, 'add_custom_messages'), 99); // add return messages for refunds
			add_action('template_redirect', array(&$this->return, 'return_page_redirect')); // throw unwelcome visitors out of return page
			add_filter('get_pages', array(&$this->return, 'return_page_hide')); // unset return page menu entry
			add_filter('the_title', array(&$this->return, 'return_page_title'), 10, 2); // set return page title manually
			add_filter('option_woocommerce_default_gateway', array(&$this, 'set_default_gateway')); // alter default gateway name

			// Shortcodes
			add_shortcode('mollie_return_page', array(&$this->return, 'return_page_render'));
		}

		return $mpm;
	}

	/**
	 * Defines admin options
	 * @return string|void
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'			=> __('Enable/Disable', 'MPM'),
				'label'			=> __('Enable Mollie Payment Module', 'MPM'),
				'default'		=> 'yes',
				'type'			=> 'checkbox',
				'description'	=> __('Enable or disable the Mollie Payment Module.', 'MPM'),
				'desc_tip'		=> true,
			),
			'api_key' => array(
				'title'			=> __('Api Key', 'MPM'),
				'default'		=> '',
				'type'			=> 'text',
				'description'	=> __('You can find your API key in your Mollie website profile. It starts with test or live. This key connects your Mollie Profile to the Woocommerce shop.', 'MPM'),
				'desc_tip'		=> true,
			),
			'description' => array(
				'title'			=> __('Description', 'MPM'),
				'default'		=> 'Order %',
				'type'			=> 'text',
				'description'	=> __('Enter a description here. Use "%" for the order id. Payment methods may have a character limit: best keep the description under 29 characters.', 'MPM'),
				'desc_tip'		=> true,
			),
			'show_images' => array(
				'title'			=> __('Show Images', 'MPM'),
				'label'			=> __('Display payment method logos', 'MPM'),
				'default'		=> 'yes',
				'type'			=> 'checkbox',
				'description'	=> __('Show or hide the payment method logos.', 'MPM'),
				'desc_tip'		=> true,
			),
			'use_profile_webhook' => array(
				'default'		=> 'no',
				'type'			=> 'hidden',
			),
		);
	}

	/**
	 * Renders admin options
	 * @return void
	 */
	public function admin_options()
	{
		parent::admin_options();
		$key = $this->get_option('api_key', '');

		if ($key != '' && strpos($key, 'live') !== 0 && strpos($key, 'test') !== 0)
		{
			echo '<div class="error">' . __('Your Api Key should start with test or live.', 'MPM') . '</div>';
		}
		else
		{
			delete_transient('mpm_api_methods');
			delete_transient('mpm_api_issuers');
		}

		if (get_option('woocommerce_currency', 'unknown') !== 'EUR')
		{
			echo '<div class="error">' . __('Warning: Mollie Payment methods are only available for payments in Euros.', 'MPM') . '</div>';
		}
		echo $this->get_update_message();
	}

	/**
	 * Extracts update information from a github releases.atom file and returns a message
	 * @return string
	 */
	public function get_update_message()
	{
		$update_message = '';
		$update_xml = $this->get_update_xml($this->update_url);
		if ($update_xml === FALSE)
		{
			$update_message = __('Warning: Could not retrieve update xml file from GitHub.');
		}
		else
		{
			/** @var SimpleXMLElement $tags */
			$tags = new SimpleXMLElement($update_xml);
			if (!empty($tags) && isset($tags->entry, $tags->entry[0], $tags->entry[0]->id))
			{
				$title = $tags->entry[0]->id;
				$latest_version = preg_replace("/[^0-9,.]/", "", substr($title, strrpos($title, '/')));
				if (!version_compare($this->plugin_version, $latest_version, '>='))
				{
					$update_message = sprintf(
						'<a href="%s/releases">' . __('You are currently using version %s. We strongly recommend you to upgrade to the new version %s!') . '</a>',
						$this->update_url, $this->plugin_version, $latest_version
					);
				}
			}
			else
			{
				$update_message = __('Warning: Update xml file from GitHub follows an unexpected format.');
			}
		}

		return $update_message;
	}

	/**
	 * Retrieves releases.atom file from github
	 * @return string
	 */
	public function get_update_xml()
	{
		return @file_get_contents($this->update_url . '/releases.atom');
	}

	/**
	 * Notifies WooCommerce that the settings page is not a payment method ;)
	 * @return bool
	 */
	public function is_available()
	{
		return FALSE;
	}

	/**
	 * Fix for the subscription plugin
	 * @see https://github.com/mollie/WooCommerce/issues/1
	 * @param $feature
	 * @return bool
	 */
	public function supports( $feature ) {
		return apply_filters( 'woocommerce_payment_gateway_supports', in_array( $feature, $this->supports ), $feature, $this );
	}

	/**
	 * We need a get_title method to prevent the settings page from breaking.
	 * This title is used in the method sorting order, it encompasses all Mollie methods
	 * @return string
	 */
	public function get_title()
	{
		return 'Mollie Payment Module';
	}

	// Payment Gateway Filters

	/**
	 * Adds either a number of MPM_Gateways classes or one MPM_Settings class to the gateway list.
	 *
	 * @param array $gateways
	 * @return array
	 */
	public function gateways_add_dynamic ($gateways)
	{
		// This is in the WooCommerce admin settings, so we'll use the Settings class instead.
		if (is_admin())
		{
			$screen = get_current_screen();

			// Add the Settings class as gateway to make it appear in the gateway settings menu.
			if (stripos($screen->id, 'wc-settings') !== FALSE)
			{
				$gateways[] = 'MPM_Settings';

				return $gateways;
			}
		}

		// Otherwise ... add payment gateways
		if ($this->get_option('enabled') === 'yes' &&
			get_option('woocommerce_currency', 'unknown') === 'EUR')
		{
			// Add as much gateways as we have payment methods (they will claim their own indices).
			foreach ($this->get_methods() as $method)
			{
				$gateways[] = 'MPM_Gateway';
			}
		}

		return $gateways;
	}

	/**
	 * Adds a number of MPM_Gateways classes to the gateway list
	 * @param array $gateways
	 * @return array
	 */
	public function gateways_add_static($gateways)
	{
		// Retrieve correct gateway position (tougher than it sounds)
		$pos_list = (array) get_option('woocommerce_gateway_order');
		$pos_orig = 0;
		if (array_key_exists('mpm', $pos_list))
		{
			$pos_orig = $pos_list['mpm'];
		}
		$pos = $pos_orig;
		$wc = WC_Payment_Gateways::instance();
		$all_gateways = $wc->payment_gateways();
		for ($i = 0; $i < $pos_orig; $i++)
		{
			// Decrease position by one for every unavailable gateway
			if (!current($all_gateways)->is_available())
			{
				$pos--;
			}
			next($all_gateways);
		}

		$mollie_gateways = array();
		if ($this->get_option('enabled') === 'yes' && get_option('woocommerce_currency', 'unknown') === 'EUR')
		{
			// Add as much gateways as we have payment methods (they will claim their own indices)
			$methods = $this->get_methods();
			for ($i = 0; $i < count($methods); $i++)
			{
				$mollie_gateways[$methods[$i]->id] = new MPM_Gateway();
			}
		}

		$head = array_slice($gateways, 0, $pos);
		$tail = array_slice($gateways, $pos);
		$gateways = array_merge($head, $mollie_gateways, $tail);
		return $gateways;
	}


	/**
	 * Retrieves and returns an order by id or false if its key is valid
	 * @param $id
	 * @param $key
	 * @param $ignore_key
	 * @return bool|WC_Order
	 */
	public function order_get($id, $key, $ignore_key = FALSE)
	{
		global $wpdb;
		$q = $wpdb->get_row("SELECT * FROM `$wpdb->posts` WHERE `post_type` = 'shop_order' AND `id` = '" . (int) $id . "'", 'ARRAY_A');
		if ($q === null)
		{
			return FALSE;
		}
		$order = new WC_Order($id);
		if (!$ignore_key && !$order->key_is_valid($key))
		{
			return FALSE;
		}
		return $order;
	}


	/**
	 * Adds a refund button to the admin order overview page
	 * @param $order
	 */
	public function show_refund_button($order)
	{
		if (get_post_meta($order->id, '_is_mollie_payment', TRUE) || !empty($_GET['force_mollie_refund']))
		{
			$url = admin_url('admin-ajax.php') . '?action=mollie_refund&id=' . $order->id . '&key=' . $order->order_key . '&nonce=' . wp_create_nonce('mollie_refund');
			echo
				'<p class="form-field form-field-wide">' .
					sprintf(__('Refund order %d online'), $order->id) . ' <br/>'  .
					'<a class="button" href="' . $url . '" onclick="return confirm(\'' . __('Are you sure you want to refund this transaction? This cannot be undone!') . '\')">' .
						__('Mollie refund') .
					'</a>' .
				'</p>';
		}
	}

	/**
	 * Adds custom messages to the end of the $messages array and store the start-index
	 * @param $messages
	 * @return array
	 */
	public function add_custom_messages($messages)
	{
		$start = sizeof($messages['shop_order']);
		update_option('woocommerce_mpm_message_start', $start);

		$messages['shop_order'][$start + 0] = __('Order refunded. The customer will receive the refunded money on the next workday.');
		$messages['shop_order'][$start + 1] = __('Refund failed!');
		if (!empty($_GET['error']))
		{
			$messages['shop_order'][$start + 1] .= __(' Reason: ') . htmlspecialchars($_GET['error']);
		}
		return $messages;
	}

	/**
	 * If default option is the Mollie Payment Module, refine it to the first available method
	 * @param string $code
	 * @return string
	 * @see https://github.com/mollie/WooCommerce/issues/2
	 */
	public function set_default_gateway($code = '')
	{
		// If on admin page, return the original value
		if (strpos($_SERVER['PHP_SELF'], 'wp-admin/admin.php') !== FALSE)
		{
			return $code;
		}
		// If on payment page and 'mpm' is selected, return correct gateway
		$methods = $this->get_methods();
		if ($code === 'mpm' && !empty($methods))
		{
			return current($methods)->id;
		}
		// Return default value
		return $code;
	}

	/**
	 * Retrieve the api client
	 * @return Mollie_API_Client|null
	 */
	public function get_api()
	{
		// Only load it if not already there
		if (is_null($this->api))
		{
			try
			{
				global $wp_version;
				$this->api = new Mollie_API_Client;
				$this->api->setApiKey($this->get_option('api_key'));
				$this->api->addVersionString('WordPress/' . (isset($wp_version) ? $wp_version : 'Unknown'));
				$this->api->addVersionString('WooCommerce/' . get_option('woocommerce_version', 'Unknown'));
				$this->api->addVersionString('MollieWoo/' . $this->plugin_version);
			}
			catch (Mollie_API_Exception $e)
			{
				$this->errors[] = __('Payment error:', 'MPM') . $e->getMessage();
				$this->display_errors();
			}
		}
		return $this->api;
	}

	/**
	 * @var NULL|Mollie_API_Object_Method[]
	 */
	private static $_methods;

	/**
	 * Retrieve the enabled payment methods from the Mollie API. Caches these for about a minute.
	 *
	 * @return array|Mollie_API_Object_List|Mollie_API_Object_Method[]
	 */
	public function get_methods()
	{
		// Retrieve the methods or fail with error
		try
		{
			if (empty(self::$_methods))
			{
				$cached = @unserialize(get_transient('mpm_api_methods'));

				if ($cached instanceof Mollie_API_Object_List)
				{
					self::$_methods = $cached;
				}
				else
				{
					self::$_methods = $this->get_api()->methods->all();
					set_transient('mpm_api_methods', self::$_methods, MINUTE_IN_SECONDS);
				}
			}

			return self::$_methods;
		}
		catch (Mollie_API_Exception $e)
		{
			$this->errors[] = __('Payment error:', 'MPM') . $e->getMessage();
			$this->display_errors();
			return array();
		}
	}

	/**
	 * @var NULL|Mollie_API_Object_Issuer[]
	 */
	private static $_issuers;

	/**
	 * Get the issuers from the Mollie API. Caches these in Wordpress cache for about a day.
	 *
	 * @param string|NULL $method Filter issuers by method
	 * @return Mollie_API_Object_Issuer[]
	 */
	public function get_issuers ($method = NULL)
	{
		// Retrieve the issuers or fail with error
		try
		{
			if (empty(self::$_issuers))
			{
				$cached = @unserialize(get_transient('mpm_api_issuers'));

				if ($cached instanceof Mollie_API_Object_List)
				{
					self::$_issuers = $cached;
				}
				else
				{
					self::$_issuers = $this->get_api()->issuers->all();
					set_transient('mpm_api_issuers', self::$_issuers, DAY_IN_SECONDS);
				}
			}

			// Filter issuers by method
			if ($method !== NULL)
			{
				$issuers = array();

				foreach(self::$_issuers AS $issuer)
				{
					if ($issuer->method === $method)
					{
						$issuers[] = $issuer;
					}
				}

				return $issuers;
			}

			return self::$_issuers;
		}
		catch (Mollie_API_Exception $e)
		{
			$this->errors[] = __('Payment error:', 'MPM') . $e->getMessage();
			$this->display_errors();
			return array();
		}
	}
}
