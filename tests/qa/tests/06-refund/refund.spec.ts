/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testRefund,
} from './_test-scenarios';
import {
	refundViaWooCommerce,
	refundViaMollieDashboard,
} from './_test-data';
import { MollieSettings, shopSettings } from '../../resources';

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
	for ( const testData of refundViaWooCommerce ) {
		// exclude tests for payment methods if not available for tested API
		const availableForApiMethods =
			testData.payment.gateway.availableForApiMethods;
		if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
			continue;
		}

		testRefund( testData );
	}

	for ( const testData of refundViaMollieDashboard ) {
		// exclude tests for payment methods if not available for tested API
		const availableForApiMethods =
			testData.payment.gateway.availableForApiMethods;
		if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
			continue;
		}

		testRefund( testData );
	}
} );
