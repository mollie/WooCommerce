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
	 * Gets formatted order lines from WooCommerce cart.
	 *
	 * @return array
	 */
	public function order_lines() {
		$this->process_cart();
		$this->process_shipping();

		$this->process_coupons();
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
	 * Process WooCommerce cart to Mollie Orders API - order lines.
	 *
	 * @access private
	 */
	private function process_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $cart_item['quantity'] ) {
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
				);

				// TODO David: Continue testing adding WooCommerce images to Mollie Orders

				$this->order_lines[] = $mollie_order_item;
			}
		}
	}

	/**
	 * Process WooCommerce shipping to Mollie Orders API - order lines.
	 *
	 * @access private
	 */
	private function process_shipping() {
		if ( WC()->shipping->get_packages() && WC()->session->get( 'chosen_shipping_methods' ) ) {
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
				)
			);

			$this->order_lines[] = $shipping;
		}
	}

	/**
	 * Process smart coupons.
	 *
	 * @access private
	 */
	private function process_coupons() {
		if ( ! empty( WC()->cart->get_coupons() ) ) {
			foreach ( WC()->cart->get_coupons() as $coupon_key => $coupon ) {
				$coupon_amount     = 0;
				$coupon_tax_amount = '';

				// Smart coupons are processed as real line items, cart and product discounts sent for reference only.
				if ( 'smart_coupon' === $coupon->get_discount_type() ) {
					$coupon_amount     = - WC()->cart->get_coupon_discount_amount( $coupon_key );
					$coupon_tax_amount = - WC()->cart->get_coupon_discount_tax_amount( $coupon_key );
				} else {
					if ( 'US' === $this->shop_country ) {
						$coupon_amount     = 0;
						$coupon_tax_amount = 0;

						if ( $coupon->is_type( 'fixed_cart' ) || $coupon->is_type( 'percent' ) ) {
							$coupon_type = 'Cart discount';
						} elseif ( $coupon->is_type( 'fixed_product' ) || $coupon->is_type( 'percent_product' ) ) {
							$coupon_type = 'Product discount';
						} else {
							$coupon_type = 'Discount';
						}

						$coupon_key = $coupon_type . ' (amount: ' . WC()->cart->get_coupon_discount_amount( $coupon_key ) . ', tax amount: ' . WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) . ')';
					}
				}

				// Add separate discount line item, but only if it's a smart coupon or country is US.
				if ( 'smart_coupon' === $coupon->get_discount_type() || 'US' === $this->shop_country ) {
					$discount = array (
						'name'        => $coupon_key,
						'quantity'    => 1,
						'unitPrice'   => array (
							'currency' => $this->currency,
							'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $coupon_amount, $this->currency ),
						),
						'vatRate'     => 0,
						'totalAmount' => array (
							'currency' => $this->currency,
							'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $coupon_amount, $this->currency ),
						),
						'vatAmount'   => array (
							'currency' => $this->currency,
							'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $coupon_tax_amount, $this->currency ),
						),
					);

					$this->order_lines[] = $discount;
				}
			} // End foreach().
		} // End if().
	}

	/**
	 * Process fees.
	 *
	 * @access private
	 */
	private function process_fees() {
		if ( ! empty( WC()->cart->get_fees() ) ) {
			foreach ( WC()->cart->get_fees() as $cart_fee ) {
				if ( $cart_fee->taxable && $cart_fee->tax > 0 ) {

					// Calculate tax rate.
					$_tax      = new WC_Tax();
					$tmp_rates = $_tax::get_rates( $cart_fee->tax_class );
					$vat       = array_shift( $tmp_rates );

					if ( isset( $vat['rate'] ) ) {
						$cart_fee_vat_rate = $vat['rate'];
					} else {
						$cart_fee_vat_rate = 0;
					}

					$cart_fee_tax_amount = $cart_fee->tax;
					$cart_fee_total      = ( $cart_fee->total + $cart_fee->tax );

				} else {
					$cart_fee_vat_rate   = 0;
					$cart_fee_tax_amount = 0;
					$cart_fee_total      = $cart_fee->total;
				}

				$fee = array (
					'type'        => 'surcharge',
					'name'        => $cart_fee->name,
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
	 * @param  array $cart_item Cart item.
	 *
	 * @return string $item_name Cart item name.
	 */
	private function get_item_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$item_name      = $cart_item_data->get_name();

		return html_entity_decode(strip_tags($item_name) );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  array $cart_item Cart item.
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
	 * @param  array  $cart_item Cart item.
	 * @param  object $product   Product object.
	 *
	 * @return integer $item_vatRate Item tax percentage formatted for Mollie Orders API.
	 */
	private function get_item_vatRate( $cart_item, $product ) {
		if ( $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
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
	 * @param  array $cart_item Cart item.
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
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_quantity Cart item quantity.
	 */
	private function get_item_quantity( $cart_item ) {
		return $cart_item['quantity'];
	}

	/**
	 * Get cart item reference.
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
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return substr( strval( $item_reference ), 0, 64 );
	}

	/**
	 * Get cart item discount.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  array $cart_item Cart item.
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
	 * @param  array $cart_item Cart item.
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
		$shipping_packages = WC()->shipping->get_packages();

		foreach ( $shipping_packages as $i => $package ) {
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