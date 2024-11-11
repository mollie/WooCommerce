/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testPaymentStatusCheckoutClassic } from './.test-scenarios';
import {
	createShopOrder,
	paymentStatusCheckoutClassicNonEur,
} from './.test-data';
import { shopSettings } from '../../resources';

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

for ( const testData of paymentStatusCheckoutClassicNonEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusCheckoutClassic( testData.testId, order );
}
