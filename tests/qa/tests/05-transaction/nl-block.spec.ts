/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import { checkoutNl, payForOrderNl } from './_test-data';

// Billink only works with an NL merchant profile, so this runs against a
// dedicated NL Mollie account/shop config (see the `transaction:nl-block` shard
// in tests/qa/project-shards.ts).
test.describe( () => {
	for ( const testData of checkoutNl ) {
		testPaymentStatusOnCheckout( testData );
	}
} );

test.describe( () => {
	for ( const testData of payForOrderNl ) {
		testPaymentStatusOnPayForOrder( testData );
	}
} );
