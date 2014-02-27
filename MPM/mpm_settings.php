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
	public $methods = array();

	public $api = null;
	public $return_page = null;
	public $hide_return_page = true;
	public $return_page_titles = array();

	public $plugin_version = 'v1.0.0';

	public function __construct()
	{
		// Enable payment methods to keep track of the settings object:
		global $mpm;
		if (!isset($mpm))
		{
			$mpm = $this;
		}
		$first = ($mpm === $this);

		// Setup settings
		$this->method_title = 'Mollie Payment Module';
		$this->id = 'mpm';
		$this->init_form_fields();
		$this->init_settings();

		// Actions
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
		add_action('template_redirect', array(&$this, 'return_page_redirect'));

		// Things to do in controller mode only:
		if ($first)
		{
			// Filters
			add_filter('woocommerce_payment_gateways', array(&$this, 'gateways_add_dynamic')); // this includes the settings page
			add_filter('woocommerce_available_payment_gateways', array(&$this, 'gateways_add_static')); // this does not include the settings page
			add_filter('get_pages', array(&$this, 'return_page_hide')); // unset return page menu entry
			add_filter('the_title', array(&$this, 'return_page_title'), 10, 2); // set return page title manually

			// Shortcodes
			add_shortcode('mollie_return_page', array(&$this, 'return_page_render'));

			// Load available methods from API
			try
			{
				global $wp_version;
				$this->api = new Mollie_API_Client;
				$this->api->setApiKey($this->get_option('api_key'));
				$this->api->addVersionString('WordPress', isset($wp_version) ? $wp_version : 'Unknown');
				$this->api->addVersionString('WooCommerce', get_option('woocommerce_version', 'Unknown'));
				$this->api->addVersionString('MollieWoo', $this->plugin_version);
				$this->methods = $this->api->methods->all();
			}
			catch (Mollie_API_Exception $e)
			{
				$this->errors[] = __('Payment error:', 'MPM') . $e->getMessage();
				$this->display_errors();
			}

			// Get return page
			$this->return_page = $this->get_return_page();

			// Create return page if not exists
			if ($this->return_page === null)
			{
				$page_data = array(
					'post_status' 		=> 'publish',
					'post_type' 		=> 'page',
					'post_author' 		=> 1,
					'post_name' 		=> 'welcome_back',
					'post_title' 		=> __('Welcome Back', 'MPM'),
					'post_content' 		=> '[mollie_return_page]',
					'post_parent' 		=> 0,
					'comment_status' 	=> 'closed'
				);
				if (!$this->is_return_page(wp_insert_post($page_data)))
				{
					$this->errors[] = __('Error: Could not find nor generate return page!', 'MPM');
					$this->display_errors();
				}
			}

			// Set return titles
			$this->return_page_titles = array(
				'pending'		=> __('Payment Pending', 'MPM'),
				'failed'		=> __('Payment Failed', 'MPM'),
				'cancelled'		=> __('Payment Cancelled', 'MPM'),
				'processing'	=> __('Processing Order', 'MPM'),
				'completed'		=> __('Order Complete', 'MPM'),
				'invalid'		=> __('Mollie Return Page', 'MPM'),
			);
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
				'label'			=> __('Display payment method logos'),
				'default'		=> 'yes',
				'type'			=> 'checkbox',
				'description'	=> __('Show or hide the payment method logos.', 'MPM'),
				'desc_tip'		=> true,
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
		// Add webhook
		echo '<div class="admin_webhook">'.__('Your webhook URL is:', 'MPM').'<br />
					<input id="mollie_webhook" value="'.admin_url('admin-ajax.php').'?action=mollie_webhook" readonly onclick="this.select();" /><br />
					<script type="text/javascript">
						var el = document.getElementById("mollie_webhook");
						el.size = el.value.length + 20;
					</script>
					<br />'.__('Copy this webhook into your <a href="https://www.mollie.nl/beheer/account/profielen/">Mollie website profile</a>.', 'MPM').'
				</div>';
		// Add warnings
		if (get_option('woocommerce_mpm_webhook_tested', 'no') !== 'yes')
		{
			echo '<div class="error">' . __('Warning: It seems you haven\'t configured your webhook in your Mollie Profile. Without webhook, orders will stay in pending status.<br />To configure your webhook, copy the above URL into your <a href="https://www.mollie.nl/beheer/account/profielen/">Mollie Profile</a>.', 'MPM') . '</div>';
		}
		if (get_option('woocommerce_currency', 'unknown') !== 'EUR')
		{
			echo '<div class="error">' . __('Warning: Mollie Payment methods are only available for payments in Euros.', 'MPM') . '</div>';
		}
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
	 * We need a get_title method to prevent the settings page from breaking.
	 * This title is used in the method sorting order, it encompasses all Mollie methods
	 * @return string
	 */
	public function get_title()
	{
		return 'Mollie Payment Methods';
	}

	// Payment Gateway Filters

	/**
	 * Adds either a number of MPM_Gateways classes or one MPM_Settings class to the gateway list (depending on context)
	 * @param array $gateways
	 * @return array
	 */
	public function gateways_add_dynamic($gateways)
	{
		// Add payment gateways, but only if we're about to pay...
		if (is_checkout() || is_ajax())
		{
			if ($this->get_option('enabled') === 'yes' && get_option('woocommerce_currency', 'unknown') === 'EUR')
			{
				// Add as much gateways as we have payment methods (they will claim their own indices)
				for ($i = count($this->methods); $i > 0; $i--)
				{
					$gateways[] = 'MPM_Gateway';
				}
			}
		}
		else // ...Otherwise, this is a settings menu and we'll use the settings class instead
		{
			// Add the settings object as gateway to make it appear in the gateway settings menu
			$gateways[] = 'MPM_Settings';
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
		if ($this->get_option('enabled') === 'yes' && get_option('woocommerce_currency', 'unknown') === 'EUR')
		{
			// Add as much gateways as we have payment methods (they will claim their own indices)
			for ($i = 0; $i < count($this->methods); $i++)
			{
				$gateways[$this->methods[$i]->id] = new MPM_Gateway();
			}
		}
		return $gateways;
	}

	// Custom return page functions

	/**
	 * Makes the return page not show up in the menu
	 * @param $pages
	 * @return mixed
	 */
	public function return_page_hide($pages)
	{
		foreach ($pages as $i => $page)
		{
			if ($this->is_return_page($page) && $this->hide_return_page)
			{
				unset($pages[$i]);
			}
		}
		return $pages;
	}

	/**
	 * Alters the return page title
	 * @param string $title
	 * @param int $id
	 * @return string
	 */
	public function return_page_title($title, $id)
	{
		if (!$this->is_return_page($id))
		{
			return $title;
		}
		if (!$order = $this->order_get($_GET['order'], $_GET['key']))
		{
			return $this->return_page_titles['invalid'];
		}
		if (!in_array($order->status, array_keys($this->return_page_titles)))
		{
			return $this->return_page_titles['invalid'];
		}
		return $this->return_page_titles[$order->status];
	}

	/**
	 * Renders the return page
	 * @return string
	 */
	public function return_page_render()
	{
		$order = $this->order_get($_GET['order'], $_GET['key']);
		if (!$order || !in_array($order->status, array_keys($this->return_page_titles)))
		{
			$html = '		<p>' . __('Your order was not recognised as being a valid order from this shop.', 'MPM') . '</p>
							<p>' . __('If you did buy something in this shop, something apparently went wrong, and you should contact us as soon as possible.', 'MPM') . '</p>';
			return $html;
		}
		$html = '<h2>'. __('Order status:', 'MPM') . ' ' . __($order->status, 'woocommerce') . '</h2>';

		switch ($order->status)
		{
			case 'pending':
			case 'on-hold':
				$html .= '	<p>' . __('We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.', 'MPM') . '</p>';
				break;
			case 'failed':
				$html .= '	<p>' . __('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction.', 'MPM') . '</p>
								<p><a href="' . esc_url($order->get_checkout_payment_url()) . '">' . __('Please attempt your purchase again', 'MPM') . '</a></p>';
				break;
			case 'cancelled':
				$html .= '	<p>' . __('You have cancelled your order.', 'MPM') . '</p>';
				break;
			case 'processing':
			case 'completed':
				$html .= '	<p>' . __('Thank you. Your order has been received.', 'MPM') . '</p>
								<ul class="order_details">
									<li class="order">' . __('Order:', 'MPM') . ' <strong>' . $order->get_order_number() . '</strong></li>
									<li class="date">' . __('Date:', 'MPM') . ' <strong>' . date_i18n(get_option('date_format'), strtotime( $order->order_date)) . '</strong></li>
									<li class="total">' . __('Total:', 'MPM') . ' <strong>' . $order->get_formatted_order_total() . '</strong></li>';
				if (isset($order->payment_method_title))
				{
					$html .= '<li class="method">' . __('Payment method:', 'MPM') . ' <strong>' . __($order->payment_method_title, 'MPM') . '</strong></li>';
				}
				$html .= '	</ul>
								<div class="clear"></div>';
				break;
		}

		ob_start();
		do_action( 'woocommerce_thankyou_' . $order->payment_method, $order->id );
		do_action( 'woocommerce_thankyou', $order->id );
		$html .= ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Determines if a certain page contains the [mollie_return_page] shorttag
	 * @param int|WP_Post $page
	 * @return bool
	 */
	public function is_return_page($page)
	{
		if (!is_a($page, 'WP_Post'))
		{
			$page = get_post($page);
		}
		if (is_null($page))
		{
			return FALSE;
		}
		return strpos($page->post_content, '[mollie_return_page]') !== FALSE;
	}

	/**
	 * Locates the return page
	 * @return int|null
	 */
	public function get_return_page()
	{
		global $wpdb;
		$q = $wpdb->get_row("SELECT `ID` FROM `$wpdb->posts` WHERE `post_status` = 'publish' AND `post_content` LIKE '%[mollie_return_page]%'", 'ARRAY_A');
		if (is_null($q) || !array_key_exists('ID', $q))
		{
			return NULL;
		}
		return $q['ID'];
	}

	/**
	 * Makes a permalink of the return page, but always uses page id (no matter the permalink format) because we're changing the return page title
	 * @return string|null
	 */
	public function get_return_link()
	{
		if (!$this->return_page)
		{
			$this->return_page = $this->get_return_page();
		}
		$post_id = $this->return_page;
		if (is_null($post_id))
		{
			$this->errors[] = __('Error: Return page not found!', 'MPM');
			$this->display_errors();
			return 'error';
		}
		return apply_filters('post_link', get_home_url(null, '?p=' . $post_id, null), $post_id, false);
	}

	/**
	 * When on return page with no query string, redirect to checkout
	 * @return void
	 */
	public function return_page_redirect()
	{
		global $mpm;
		if (is_page($mpm->return_page))
		{
			$order_id = (int) $_GET['order'];
			$key = $_GET['key'];
			if (!$order_id || !$order = $this->order_get($order_id, $key))
			{
				wp_redirect(get_permalink(woocommerce_get_page_id('checkout')));
				exit;
			}
		}
	}

	/**
	 * Get the order
	 */
	public function order_get($id, $key)
	{
		global $wpdb;
		$q = $wpdb->get_row("SELECT * FROM `$wpdb->posts` WHERE `post_type` = 'shop_order' AND `id` = '" . (int) $id . "'", 'ARRAY_A');
		if ($q === null)
		{
			return FALSE;
		}
		$order = new WC_Order($id);
		if (!$order->key_is_valid($key))
		{
			return FALSE;
		}
		return $order;
	}
}