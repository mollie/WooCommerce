/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import { checkoutEur, payForOrderEur } from './_test-data';
import { shopConfigGermany } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( shopConfigGermany );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

test.describe( () => {
	for ( const testData of checkoutEur ) {
		testPaymentStatusOnCheckout( testData );
	}
} );

test.describe( () => {
	for ( const testData of payForOrderEur ) {
		testPaymentStatusOnPayForOrder( testData );
	}
} );
