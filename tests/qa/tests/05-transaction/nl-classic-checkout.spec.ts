/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { classicCheckoutNl } from './_test-data';

// Billink only works with an NL merchant profile - the `setup-mollie-nl` project
// dependency already handles the NL shop config/Mollie reconnect, this only
// needs to additionally switch to classic cart/checkout pages.
test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
} );

for ( const testData of classicCheckoutNl ) {
	testPaymentStatusOnClassicCheckout( testData );
}
