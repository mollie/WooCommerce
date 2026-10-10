/**
 * Internal dependencies
 */
import { testSubscriptionRenewal } from './_test-scenarios';
import { subscriptionRenewal } from './_test-data';

for ( const testOrder of subscriptionRenewal ) {
	testSubscriptionRenewal( testOrder );
}
