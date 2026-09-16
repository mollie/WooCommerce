/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testLateWebhookAfterRefundOnCheckout } from './_test-scenarios';
import { lateWebhookAfterRefundEur } from './_test-data';
import { shopConfigDefault } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( shopConfigDefault );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const testData of lateWebhookAfterRefundEur ) {
	testLateWebhookAfterRefundOnCheckout( testData );
}
