<?php

class Mollie_WC_Helper_PaymentFactory {

	public static function getPaymentObject( $data ) {

		if ( ( ! is_object( $data ) && $data == 'order' ) || ( ! is_object( $data ) && strpos( $data, 'ord_' ) !== false ) || ( is_object( $data ) && $data->resource == 'order' )  ) {
			return new Mollie_WC_Payment_Order( $data );
		}

		if ( ( ! is_object( $data ) && $data == 'payment' ) || ( ! is_object( $data ) && strpos( $data, 'tr_' ) !== false ) || ( is_object( $data ) && $data->resource == 'payment' )  ) {
			return new Mollie_WC_Payment_Payment( $data );
		}

		return false;
	}

}

