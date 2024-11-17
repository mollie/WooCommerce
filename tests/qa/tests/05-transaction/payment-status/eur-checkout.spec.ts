/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { testPaymentStatusOnCheckout } from './.test-scenarios';
import { createShopOrder, checkoutEur } from './.test-data';
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

for ( const testData of checkoutEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnCheckout( testData.testId, order );
}
