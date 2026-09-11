/**
 * Internal dependencies
 */
import { testPaymentStatusOnCheckout } from './_test-scenarios';
import { checkoutNl } from './_test-data';

// Billink only works with an NL merchant profile, so this runs against a
// dedicated NL Mollie account/shop config (see the `setup-mollie-nl` project
// dependency) rather than the default German one the rest of 05-transaction uses.
for ( const testData of checkoutNl ) {
	testPaymentStatusOnCheckout( testData );
}
