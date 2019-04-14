<?php

class Mollie_WC_Helper_OrderLines {

	/**
	 * Formatted order lines.
	 *
	 * @var $order_lines
	 */
	private $order_lines = array ();

	/**
	 * WooCommerce order.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * WooCommerce currency.
	 *
	 */
	private $currency;

	/**
	 * Shop country.
	 *
	 * @var string
	 */
	private $shop_country;

	/**
	 * Mollie_WC_Helper_Order_Lines constructor.
	 *
	 * @param bool|string $shop_country Shop country.
	 * @param object      $order        WooCommerce Order
	 */
	public function __construct( $shop_country, $order ) {
		$this->shop_country = $shop_country;

		$this->order    = $order;
		$this->currency = Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $this->order );

	}

	/**
	 * Gets formatted order lines from WooCommerce order.
	 *
	 * @return array
	 */
	public function order_lines() {

		$this->process_items();
		$this->process_shipping();
		$this->process_fees();

		return array (
			'lines' => $this->get_order_lines(),
		);
	}

	/**
	 * Get order lines formatted for Mollie Orders API.
	 *
	 * @access private
	 * @return mixed
	 */
	private function get_order_lines() {
		return $this->order_lines;
	}

	/**
	 * Process WooCommerce order items to Mollie Orders API - order lines.
	 *
	 * @access private
	 */
	private function process_items() {
		foreach ( $this->order->get_items() as $cart_item ) {

			if ( $cart_item['quantity'] ) {

				do_action( Mollie_WC_Plugin::PLUGIN_ID . '_orderlines_process_items_before_getting_product_id', $cart_item );

				if ( $cart_item['variation_id'] ) {
					$product = wc_get_product( $cart_item['variation_id'] );
				} else {
					$product = wc_get_product( $cart_item['product_id'] );
				}

				$this->currency = Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $this->order );

				$mollie_order_item = array (
					'sku'            => $this->get_item_reference( $product ),
					'name'           => $this->get_item_name( $cart_item ),
					'quantity'       => $this->get_item_quantity( $cart_item ),
					'vatRate'        => $this->get_item_vatRate( $cart_item, $product ),
					'unitPrice'      => array (
						'currency' => $this->currency,
						'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_item_price( $cart_item ), $this->currency ),
					),
					'totalAmount'    => array (
						'currency' => $this->currency,
						'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_item_total_amount( $cart_item ), $this->currency ),
					),
					'vatAmount'      =>
						array (
							'currency' => $this->currency,
							'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_item_tax_amount( $cart_item ), $this->currency ),
						),
					'discountAmount' =>
						array (
							'currency' => $this->currency,
							'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_item_discount_amount( $cart_item ), $this->currency ),
						),
					'metadata' =>
						array(
							'order_item_id' => $cart_item->get_id(),
						),
				);

				// TODO David: Continue testing adding WooCommerce images to Mollie Orders

				$this->order_lines[] = $mollie_order_item;

				do_action( Mollie_WC_Plugin::PLUGIN_ID . '_orderlines_process_items_after_processing_item', $cart_item );
			}
		}
	}

	/**
	 * Process WooCommerce shipping to Mollie Orders API - order lines.
	 *
	 * @access private
	 */
	private function process_shipping() {
		if ( $this->order->get_shipping_methods() && WC()->session->get( 'chosen_shipping_methods' ) ) {

			$shipping = array (
				'type'        => 'shipping_fee',
				'name'        => $this->get_shipping_name(),
				'quantity'    => 1,
				'vatRate'     => $this->get_shipping_vat_rate(),
				'unitPrice'   => array (
					'currency' => $this->currency,
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_shipping_amount(), $this->currency ),
				),
				'totalAmount' => array (
					'currency' => $this->currency,
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_shipping_amount(), $this->currency ),
				),
				'vatAmount'   => array (
					'currency' => $this->currency,
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $this->get_shipping_tax_amount(), $this->currency ),
				),
				'metadata'    => array (
					'order_item_id' => $this->get_shipping_id(),
				),
			);

			$this->order_lines[] = $shipping;
		}
	}

	/**
	 * Process fees.
	 *
	 * @access private
	 */
	private function process_fees() {

		if ( ! empty( $this->order->get_items( 'fee' )  ) ) {
			foreach ( $this->order->get_items( 'fee' ) as $cart_fee ) {

				if ( $cart_fee['tax_status'] == 'taxable' && $cart_fee['total_tax'] > 0 ) {

					// Calculate tax rate.
					$_tax      = new WC_Tax();
					$tmp_rates = $_tax::get_rates( $cart_fee['tax_class'] );
					$vat       = array_shift( $tmp_rates );

					if ( isset( $vat['rate'] ) ) {
						$cart_fee_vat_rate = $vat['rate'];
					} else {
						$cart_fee_vat_rate = 0;
					}

					$cart_fee_tax_amount = $cart_fee['total_tax'];
					$cart_fee_total      = ( $cart_fee['total'] + $cart_fee['total_tax'] );

				} else {
					$cart_fee_vat_rate   = 0;
					$cart_fee_tax_amount = 0;
					$cart_fee_total      = $cart_fee['total'];
				}

				$fee = array (
					'type'        => 'surcharge',
					'name'        => $cart_fee['name'],
					'quantity'    => 1,
					'vatRate'     => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $cart_fee_vat_rate, $this->currency ),
					'unitPrice'   => array (
						'currency' => $this->currency,
						'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $cart_fee_total, $this->currency ),
					),
					'totalAmount' => array (
						'currency' => $this->currency,
						'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $cart_fee_total, $this->currency ),
					),
					'vatAmount'   => array (
						'currency' => $this->currency,
						'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $cart_fee_tax_amount, $this->currency ),
					),
					'metadata'    => array (
						'order_item_id' => $cart_fee->get_id(),
					),
				);

				$this->order_lines[] = $fee;
			} // End foreach().
		} // End if().
	}

	// Helpers.

	/**
	 * Get cart item name.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item $cart_item Cart item.
	 *
	 * @return string $item_name Cart item name.
	 */
	private function get_item_name( $cart_item ) {
		$item_name      = $cart_item->get_name();

		return html_entity_decode(strip_tags($item_name) );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item $cart_item Cart item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	private function get_item_tax_amount( $cart_item ) {
		$item_tax_amount = $cart_item['line_tax'];

		return $item_tax_amount;
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item  $cart_item Cart item.
	 * @param  object $product   Product object.
	 *
	 * @return integer $item_vatRate Item tax percentage formatted for Mollie Orders API.
	 */
	private function get_item_vatRate( $cart_item, $product ) {
		if ( $product && $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate.

			$_tax      = new WC_Tax();
			$tmp_rates = $_tax->get_rates( $product->get_tax_class() );
			$vat       = array_shift( $tmp_rates );

			if ( isset( $vat['rate'] ) ) {
				$item_vatRate = $vat['rate'];
			} else {
				$item_vatRate = 0;
			}

		} else {
			$item_vatRate = 0;
		}

		return $item_vatRate;
	}

	/**
	 * Get cart item price.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item $cart_item Cart item.
	 *
	 * @return integer $item_price Cart item price.
	 */
	private function get_item_price( $cart_item ) {

		$item_subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
		$item_price    = $item_subtotal / $cart_item['quantity'];

		return $item_price;
	}

	/**
	 * Get cart item quantity.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item $cart_item Cart item.
	 *
	 * @return integer $item_quantity Cart item quantity.
	 */
	private function get_item_quantity( $cart_item ) {
		return $cart_item['quantity'];
	}

	/**
	 * Get cart item SKU.
	 *
	 * Returns SKU or product ID.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  object $product Product object.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	private function get_item_reference( $product ) {
		if ( $product && $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} elseif ( $product ) {
			$item_reference = $product->get_id();
		} else {
			$item_reference = '';
		}

		return substr( strval( $item_reference ), 0, 64 );
	}

	/**
	 * Get cart item discount.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item $cart_item Cart item.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	private function get_item_discount_amount( $cart_item ) {
		if ( $cart_item['line_subtotal'] > $cart_item['line_total'] ) {

			$item_discount_amount = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] - $cart_item['line_total'] - $cart_item['line_tax'];

		} else {
			$item_discount_amount = 0;
		}

		return $item_discount_amount;
	}

	/**
	 * Get cart item total amount.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  WC_Order_Item $cart_item Cart item.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 */
	private function get_item_total_amount( $cart_item ) {

		$item_total_amount = ( ( $cart_item['line_total'] + $cart_item['line_tax'] ) );

		return $item_total_amount;
	}

	/**
	 * Get shipping method name.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @return string $shipping_name Name for selected shipping method.
	 */
	private function get_shipping_name() {

		foreach ( $this->order->get_items( 'shipping' ) as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_name = $rate_value->label;
					}
				}
			}
		}

		if ( ! isset( $shipping_name ) ) {
			$shipping_name = __( 'Shipping', 'mollie-payments-for-woocommerce' );
		}

		return (string) $shipping_name;
	}


	/**
	 * Get shipping method name.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @return string $shipping_name Name for selected shipping method.
	 */
	private function get_shipping_id() {
		$shipping_id = '';

		foreach ( $this->order->get_items( 'shipping' ) as $i => $package ) {

			$shipping_id = $package->get_id();

		}

		return (string) $shipping_id;
	}

	/**
	 * Get shipping method amount.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @return integer $shipping_amount Amount for selected shipping method.
	 */
	private function get_shipping_amount() {

		$shipping_amount = number_format( ( WC()->cart->shipping_total + WC()->cart->shipping_tax_total ), 2, '.', '' );

		return $shipping_amount;
	}

	/**
	 * Get shipping method tax rate.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @return integer $shipping_vat_rate Tax rate for selected shipping method.
	 */
	private function get_shipping_vat_rate() {
		$shipping_vat_rate = 0;
		if ( WC()->cart->shipping_tax_total > 0 ) {
			$shipping_vat_rate = round( WC()->cart->shipping_tax_total / WC()->cart->shipping_total, 2 ) * 100;
		}

		return $shipping_vat_rate;
	}

	/**
	 * Get shipping method tax amount.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @return integer $shipping_tax_amount Tax amount for selected shipping method.
	 */
	private function get_shipping_tax_amount() {

		$shipping_tax_amount = WC()->cart->shipping_tax_total;

		return $shipping_tax_amount;
	}

}
