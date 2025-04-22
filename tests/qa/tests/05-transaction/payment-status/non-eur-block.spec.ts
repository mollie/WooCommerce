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
import { MollieSettings, shopSettings } from '../../../resources';

const apiMethod = process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod;

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

	// exclude tests for payment methods if not available for tested API
	const availableForApiMethods = order.payment.gateway.availableForApiMethods;
	if ( ! availableForApiMethods.includes( apiMethod ) ) {
		continue;
	}

	testPaymentStatusOnCheckout( testData.testId, order );
}

for ( const testData of payForOrderNonEur ) {
	const order = createShopOrder( testData );

	// exclude tests for payment methods if not available for tested API
	const availableForApiMethods = order.payment.gateway.availableForApiMethods;
	if ( ! availableForApiMethods.includes( apiMethod ) ) {
		continue;
	}

	testPaymentStatusOnPayForOrder( testData.testId, order );
}
