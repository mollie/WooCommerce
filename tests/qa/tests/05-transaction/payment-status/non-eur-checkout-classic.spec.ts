/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { testPaymentStatusOnClassicCheckout } from './.test-scenarios';
import { createShopOrder, classicCheckoutNonEur } from './.test-data';
import { shopSettings } from '../../../resources';

test.beforeAll( async ( { utils }, testInfo ) => {
	if ( testInfo.project.name !== 'all' ) {
		return;
	}
	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
		classicPages: true,
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of classicCheckoutNonEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnClassicCheckout( testData.testId, order );
}
