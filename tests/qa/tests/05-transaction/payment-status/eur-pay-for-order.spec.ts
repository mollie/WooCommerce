/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { testPaymentStatusOnPayForOrder } from './.test-scenarios';
import { createShopOrder, payForOrderEur } from './.test-data';
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

for ( const testData of payForOrderEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnPayForOrder( testData.testId, order );
}
