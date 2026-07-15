/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { products, shopConfigDefault } from '../../resources';
import { testSubscriptionRenewal } from './_test-scenarios';
import { subscriptionRenewal } from './_test-data';

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

for ( const testOrder of subscriptionRenewal ) {
	testSubscriptionRenewal( testOrder );
}
