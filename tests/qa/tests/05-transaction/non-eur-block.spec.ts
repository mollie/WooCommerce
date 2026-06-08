/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import { checkoutNonEur, payForOrderNonEur } from './_test-data';

test.describe( () => {
	for ( const testData of checkoutNonEur ) {
		testPaymentStatusOnCheckout( testData );
	}
} );

test.describe( () => {
	for ( const testData of payForOrderNonEur ) {
		testPaymentStatusOnPayForOrder( testData );
	}
} );
