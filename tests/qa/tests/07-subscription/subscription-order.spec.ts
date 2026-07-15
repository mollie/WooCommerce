/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { products, shopConfigDefault } from '../../resources';
import { testSubscriptionOrderOnClassicCheckout } from './_test-scenarios';
import { subscriptionOrderOnClassicCheckout } from './_test-data';

test.beforeAll( async ( { utils } ) => {
	test.setTimeout( 2 * 60_000 );
	await utils.configureStore( {
		...shopConfigDefault,
		enableClassicPages: true,
		enableSubscriptionsPlugin: true,
		products: [ products.mollieSubscription100 ],
	} );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of subscriptionOrderOnClassicCheckout ) {
	testSubscriptionOrderOnClassicCheckout( testData );
}
