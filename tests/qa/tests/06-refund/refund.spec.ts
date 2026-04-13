/**
 * Internal dependencies
 */
import { testRefund } from './_test-scenarios';
import { refundViaWooCommerce, refundViaMollieDashboard } from './_test-data';

for ( const testData of refundViaWooCommerce ) {
	testRefund( testData );
}

for ( const testData of refundViaMollieDashboard ) {
	testRefund( testData );
}
