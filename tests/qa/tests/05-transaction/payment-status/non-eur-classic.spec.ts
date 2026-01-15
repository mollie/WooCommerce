/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { classicCheckoutNonEur } from './_test-data';
import { MollieSettings, shopSettings } from '../../../resources';
import { createShopOrder } from '../../../utils/data-conversion';

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
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of classicCheckoutNonEur ) {
	const order = createShopOrder( testData );

	// exclude tests for payment methods if not available for tested API
	const availableForApiMethods = order.payment.gateway.availableForApiMethods;
	if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
		continue;
	}

	testPaymentStatusOnClassicCheckout( testData.testId, order );
}
