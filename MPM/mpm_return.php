<?php

class MPM_return extends MPM_Settings
{
	public $return_page            = null;
	public $hide_return_page       = true;
	public $return_page_titles     = array();
	public $wc_order_received_page = null;

	public function __construct()
	{
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
			if (!$this->is_return_page_2_1(wp_insert_post($page_data)))
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

	/**
	 * Text displayed on order-receive page from WC.
	 * @param string $text
	 * @param WC_Order|null $order
	 * @return string
	 */
	public function return_page_order_received_text($text, WC_Order $order = null)
	{
		// first of all, did we use mollie as a payment method?
		$isMolliePayment = get_post_meta($order->id,'_is_mollie_payment');
		if (!$isMolliePayment || !is_array($isMolliePayment) || count($isMolliePayment) == 0 || !$isMolliePayment[0])
		{
			return $text;
		}

		return $this->return_page_status($order);
	}

	/**
	 * Status displayed on mollie return page.
	 * @param string $text
	 * @param WC_Order|null $order
	 * @return string
	 */
	public function return_page_status($order, $redirect = true)
	{
		// Do we know that the key is checked by WC? check it for sure.
		$order = $this->order_get(($order != null) ? $order->id : 0, $_GET['key']);

		if (!$order)
		{
			$html = '		<p>' . __('Your order was not recognised as being a valid order from this shop.', 'MPM') . '</p>
							<p>' . __('If you did buy something in this shop, something apparently went wrong, and you should contact us as soon as possible.', 'MPM') . '</p>';
			return $html;
		}

		$order_status = in_array($order->status, array_keys($this->return_page_titles))
			? $this->return_page_titles[$order->status]
			: wc_get_order_status_name($order->status);
		$html = '<h2>'. __('Order status:', 'MPM') . ' ' . $order_status . '</h2>';

		// if user cancelled at mollie or issuer, webhook will render post_meta: _is_mollie_cancelled true.
		$isCancelled = get_post_meta($order->id, '_is_mollie_cancelled');

		switch ($order->status)
		{
			case 'pending':
				// if user cancelled the order will stay pending and isCancelled is true. We redirect the user to the payment page.
				if ($isCancelled)
				{
					if ($redirect)
					{
						wp_redirect($order->get_checkout_payment_url());
					}
					$html .= '	<p>' . __('You have cancelled your order.', 'MPM') . '</p>
					<p><a href="' . esc_url($order->get_checkout_payment_url()) . '">' . __('Please attempt your purchase again', 'MPM') . '</a></p>';
				}
				else
				{
					$html .= '	<p>' . __('We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.', 'MPM') . '</p>';
				}
				break;
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
								<div class="clear"></div>';
				break;
		}

		return $html;
	}

	/**
	 * Alters the return page title.
	 *
	 * @param string $title
	 * @param int $id (Optional) Default NULL.
	 * @return string
	 */
	public function return_page_title ($title, $id = NULL)
	{
		// first of all, did we use mollie as a payment method?
		$isMolliePayment = get_post_meta($this->get_order_id_from_request(),'_is_mollie_payment');
		if (!$isMolliePayment || !is_array($isMolliePayment) || count($isMolliePayment) == 0 || !$isMolliePayment[0])
		{
			return $title;
		}

		// so if not on the checkout/order-receive page. return title.
		if (!$this->is_return_page($id))
		{
			return $title;
		}

				// if no key isset than we have an invalid request
		if (!isset($_GET['key']))
		{
			return $this->return_page_titles['invalid'];
		}

				// try retrieve order or we have an invalid request. (invalid id or key)
		if (!$order = $this->order_get($this->get_order_id_from_request(), $_GET['key']))
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
	 * Determines if we are on the checkout AND the order-receive endpoint
	 * @param int|WP_Post $page
	 * @return bool
	 */
	public function is_return_page($page)
	{
		$receive_page = $this->get_wc_order_received_page();

		// are we on the checkout endpoint?
		if (woocommerce_get_page_id('checkout') == $page)
		{
			// we are on the checkout and the receive-order isset. so yes
			if (isset($_GET[$receive_page]))
			{
				return true;
			}
			else
			{
				// we are on the checkout endoint but no receive-order isset
				// check if we have SEO url and retrieve it from the URI
				$current_url = $_SERVER['REQUEST_URI'];
				$pos = strpos($current_url, $receive_page);
				if ($pos !== FALSE)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * When on return page with no query string, redirect to checkout
	 * @return void
	 */
	public function return_page_redirect()
	{
		// is_return_page also checks for order-receive endpoint.
		// so we force this with the checkout page id.
		if ($this->is_return_page(woocommerce_get_page_id('checkout')))
		{
			$order_id = $this->get_order_id_from_request();
			$key = (isset($_GET['key'])) ? $_GET['key'] : null;
						// only when we dont have id or key we redirect..
			if ($order_id == null || $key == null)
			{
				wp_redirect(get_permalink(woocommerce_get_page_id('checkout')));
				exit;
			}
		}

		// When on the mollie return page .. and order == pending && cancelled.. 
		// redirect here because headers are allready sent on shortcode.
		if (is_page($this->return_page))
		{
			$order_id = (int) $_GET['order'];
			$key = $_GET['key'];
			if (!$order_id || !$order = $this->order_get($order_id, $key))
			{
				wp_redirect(get_permalink(woocommerce_get_page_id('checkout')));
				exit;
			}

			if ($order->status == 'pending')
			{
				// check if cancelled..
				$isCancelled = get_post_meta($order->id, '_is_mollie_cancelled');
				if ($isCancelled)
				{
					// on mollie return page, has order, order is pending, and meta cancelled is true.
					wp_redirect($order->get_checkout_payment_url());
					exit;
				}
			}
		}

	}

	/**
	 * returns the woocommerce order received endpoint. this can be set in
	 * the woocommerce admin -> settings -> checkout
	 * @return string
	 */
	public function get_wc_order_received_page()
	{
		if ($this->wc_order_received_page == null)
		{
			$this->wc_order_received_page = get_option('woocommerce_checkout_order_received_endpoint', 'order-received' );
		}
		return $this->wc_order_received_page;
	}

	/**
	 * Gets the order id from the request url.
	 * If not permalink it's in the GET array
	 * If it is a permalink. then parse the id.
	 * @return string|null
	 */
	public function get_order_id_from_request()
	{
		$receive_page = $this->get_wc_order_received_page();
		if (isset($_GET[$receive_page]))
		{
			// no permalink and we have an id.
			return $_GET[$receive_page];
		}
		else
		{
			// no order-receive id in _GET array. check for id in the SEO url.
			$current_url = $_SERVER['REQUEST_URI'];
			$pos = strpos($current_url, $receive_page);
			if ($pos !== FALSE)
			{
				$length = strlen($receive_page);
				$char = $pos + $length + 1;
				$char_end = strpos(substr($current_url, $char), '/');
				if (!$char_end)
				{
					$char_end = strpos(substr($current_url, $char), '?');
				}
				return substr($current_url, $char,$char_end);
			}
		}
		return null;
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
	 * Determines if a certain page contains the [mollie_return_page] shorttag
	 * @param int|WP_Post $page
	 * @return bool
	 */
	public function is_return_page_2_1($page)
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
		return get_permalink($post_id);
	}

	/**
	 * Renders the return page
	 * @return string
	 */
	public function return_page_render()
	{
		$order = $this->order_get($_GET['order'], $_GET['key'], true);
		$html = $this->return_page_status($order, false);

		ob_start();
		do_action( 'woocommerce_thankyou_' . $order->payment_method, $order->id );
		do_action( 'woocommerce_thankyou', $order->id );
		$html .= ob_get_contents();
		ob_end_clean();

		return $html;
	}
}