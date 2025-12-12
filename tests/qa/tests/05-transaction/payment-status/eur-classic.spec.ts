/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { createShopOrder, classicCheckoutEur } from './_test-data';
import { MollieSettings, shopSettings } from '../../../resources';

const testedApiMethod =
	( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) || 'payment';

test.beforeAll( async ( { utils }, testInfo ) => {
	if ( testInfo.project.name !== 'all' ) {
		return;
	}

	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
		enableClassicPages: true,
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of classicCheckoutEur ) {
	const order = createShopOrder( testData );

	// exclude tests for payment methods if not available for tested API
	const availableForApiMethods = order.payment.gateway.availableForApiMethods;
	if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
		continue;
	}

	testPaymentStatusOnClassicCheckout( testData.testId, order );
}
