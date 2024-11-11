/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testPaymentStatusPayForOrder } from './.test-scenarios';
import { createShopOrder, paymentStatusPayForOrderNonEur } from './.test-data';
import { shopSettings } from '../../resources';

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

for ( const testData of paymentStatusPayForOrderNonEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusPayForOrder( testData.testId, order );
}
