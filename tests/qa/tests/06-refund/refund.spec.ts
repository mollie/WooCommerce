/**
 * Internal dependencies
 */
import { testRefund } from './_test-scenarios';
import { refundViaWooCommerce, refundViaMollieDashboard } from './_test-data';
import { MollieSettings } from '../../resources';

const testedApiMethod =
	( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) || 'payment';

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
