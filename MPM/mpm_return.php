<?php

class MPM_return extends MPM_Settings
{
	public $return_page            = null;
	public $hide_return_page       = true;
	public $return_page_titles     = array();
	public $wc_order_received_page = null;

	public function __construct()
	{
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

		// Do we know that the key is checked by WC? check it for sure.
		$order = $this->order_get(($order != null) ? $order->id : 0, $_GET['key']);

		if (!$order || !in_array($order->status, array_keys($this->return_page_titles)))
		{
			$html = '		<p>' . __('Your order was not recognised as being a valid order from this shop.', 'MPM') . '</p>
							<p>' . __('If you did buy something in this shop, something apparently went wrong, and you should contact us as soon as possible.', 'MPM') . '</p>';
						return $html;
		}
		$html = '<h2>'. __('Order status:', 'MPM') . ' ' . $this->return_page_titles[$order->status] . '</h2>';

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
}