( function ( { jQuery } ) {
	jQuery( function ( $ ) {
		$( 'body' ).on( 'change', '#billing_country', function () {
			if (
				$( 'input[name="payment_method"]:checked' ).val() ===
				'mollie_wc_gateway_riverty'
			) {
				$( 'body' ).trigger( 'update_checkout' );
			}
		} );
	} );
} )( window );
