/**
 * Internal dependencies
 */
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { classicCheckoutNonEur } from './_test-data';

for ( const testData of classicCheckoutNonEur ) {
	testPaymentStatusOnClassicCheckout( testData );
}
