/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { classicCheckoutNonEur } from './_test-data';
import { shopConfigClassic } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( shopConfigClassic );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of classicCheckoutNonEur ) {
	testPaymentStatusOnClassicCheckout( testData );
}
