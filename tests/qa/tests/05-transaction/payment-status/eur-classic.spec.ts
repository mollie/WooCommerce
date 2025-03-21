/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { createShopOrder, classicCheckoutEur } from './_test-data';
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

for ( const testData of classicCheckoutEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnClassicCheckout( testData.testId, order );
}
