<?php

class MPM_return extends MPM_Settings
{
	public $return_page = null;
	public $hide_return_page = true;
	public $return_page_titles = array();

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
	 * Alters the return page title.
	 *
	 * @param string $title
	 * @param int $id (Optional) Default NULL.
	 * @return string
	 */
	public function return_page_title ($title, $id = NULL)
	{
		if (!$this->is_return_page($id))
		{
			return $title;
		}

		if (!isset($_GET['order']) || !isset($_GET['key']))
		{
			return $this->return_page_titles['invalid'];
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
		return get_permalink($post_id);
	}

	/**
	 * When on return page with no query string, redirect to checkout
	 * @return void
	 */
	public function return_page_redirect()
	{
		if (is_page($this->return_page))
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
}