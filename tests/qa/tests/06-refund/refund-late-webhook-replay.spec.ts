/**
 * Internal dependencies
 */
import { testLateWebhookAfterRefundOnCheckout } from './_test-scenarios';
import { lateWebhookAfterRefundEur } from './_test-data';

for ( const testData of lateWebhookAfterRefundEur ) {
	testLateWebhookAfterRefundOnCheckout( testData );
}
