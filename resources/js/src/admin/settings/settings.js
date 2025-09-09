( function ( { _, mollieSettingsData, jQuery } ) {
	const { current_section = false } = mollieSettingsData;
	jQuery( function ( $ ) {
		if ( _.isEmpty( mollieSettingsData ) ) {
			return;
		}
		const gatewayName = current_section;
		if ( ! gatewayName ) {
			return;
		}
		const fixedField = $( '#' + gatewayName + '_fixed_fee' ).closest(
			'tr'
		);
		const percentField = $( '#' + gatewayName + '_percentage' ).closest(
			'tr'
		);
		const limitField = $( '#' + gatewayName + '_surcharge_limit' ).closest(
			'tr'
		);
		const maxField = $( '#' + gatewayName + '_maximum_limit' ).closest(
			'tr'
		);

		$( '#' + gatewayName + '_payment_surcharge' )
			.change( function () {
				switch ( $( this ).val() ) {
					case 'no_fee':
						fixedField.hide();
						percentField.hide();
						limitField.hide();
						maxField.hide();
						break;
					case 'fixed_fee':
						fixedField.show();
						maxField.show();
						percentField.hide();
						limitField.hide();
						break;
					case 'percentage':
						fixedField.hide();
						maxField.show();
						percentField.show();
						limitField.show();
						break;
					case 'fixed_fee_percentage':
					default:
						fixedField.show();
						percentField.show();
						limitField.show();
						maxField.show();
				}
			} )
			.change();
	} );
} )( window );
