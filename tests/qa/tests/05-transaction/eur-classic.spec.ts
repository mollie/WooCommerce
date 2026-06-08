/**
 * Internal dependencies
 */
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { classicCheckoutEur } from './_test-data';

for ( const testData of classicCheckoutEur ) {
	testPaymentStatusOnClassicCheckout( testData );
}
