/**
 * Internal dependencies
 */
import { testSubscriptionOrderOnClassicCheckout } from './_test-scenarios';
import { subscriptionOrderOnClassicCheckout } from './_test-data';

for ( const testData of subscriptionOrderOnClassicCheckout ) {
	testSubscriptionOrderOnClassicCheckout( testData );
}
