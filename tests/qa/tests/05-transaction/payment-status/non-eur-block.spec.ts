/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import {
	createShopOrder,
	checkoutNonEur,
	payForOrderNonEur,
} from './_test-data';
import { shopSettings } from '../../../resources';

test.beforeAll( async ( { utils }, testInfo ) => {
	if ( testInfo.project.name !== 'all' ) {
		return;
	}
	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
		classicPages: false,
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of checkoutNonEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnCheckout( testData.testId, order );
}

for ( const testData of payForOrderNonEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnPayForOrder( testData.testId, order );
}
