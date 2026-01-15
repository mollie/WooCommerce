/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import {
	checkoutNonEur,
	payForOrderNonEur,
} from './_test-data';
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
		enableClassicPages: false,
	} );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

test.describe( () => {
	for ( const testData of checkoutNonEur ) {
		const order = createShopOrder( testData );

		// exclude tests for payment methods if not available for tested API
		const availableForApiMethods =
			order.payment.gateway.availableForApiMethods;
		if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
			continue;
		}

		testPaymentStatusOnCheckout( testData.testId, order );
	}
} );

test.describe( () => {
	for ( const testData of payForOrderNonEur ) {
		const order = createShopOrder( testData );

		// exclude tests for payment methods if not available for tested API
		const availableForApiMethods =
			order.payment.gateway.availableForApiMethods;
		if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
			continue;
		}

		testPaymentStatusOnPayForOrder( testData.testId, order );
	}
} );
