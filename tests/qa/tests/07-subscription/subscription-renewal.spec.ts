/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { products, shopConfigGermany } from '../../resources';
import { testSubscriptionRenewal } from './_test-scenarios';
import { subscriptionRenewal } from './_test-data';

test.beforeAll( async ( { utils, wooCommerceApi } ) => {
	test.setTimeout( 2 * 60_000 );
	await utils.configureStore( {
		...shopConfigGermany,
		enableClassicPages: true,
		enableSubscriptionsPlugin: true,
		products: [ products.mollieSubscription100 ],
	} );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of subscriptionRenewal ) {
	testSubscriptionRenewal( testOrder );
}
