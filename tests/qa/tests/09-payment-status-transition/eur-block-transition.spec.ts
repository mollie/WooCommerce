/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testOrderStatusTransitionOnCheckout } from './_test-scenarios';
import { checkoutTransitionsEur } from './_test-data';
import { shopConfigDefault } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( shopConfigDefault );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of checkoutTransitionsEur ) {
	testOrderStatusTransitionOnCheckout( testData );
}
