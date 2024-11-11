/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testPaymentStatusCheckout } from './.test-scenarios';
import { createShopOrder, paymentStatusCheckoutNonEur } from './.test-data';
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

for ( const testData of paymentStatusCheckoutNonEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusCheckout( testData.testId, order );
}
