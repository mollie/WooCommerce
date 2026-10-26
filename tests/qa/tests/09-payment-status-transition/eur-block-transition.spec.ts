/**
 * Internal dependencies
 */
import { testOrderStatusTransitionOnCheckout } from './_test-scenarios';
import { checkoutTransitionsEur } from './_test-data';

for ( const testData of checkoutTransitionsEur ) {
	testOrderStatusTransitionOnCheckout( testData );
}
