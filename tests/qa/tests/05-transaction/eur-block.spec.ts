/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import { checkoutEur, payForOrderEur } from './_test-data';

test.describe( () => {
	for ( const testData of checkoutEur ) {
		testPaymentStatusOnCheckout( testData );
	}
} );

test.describe( () => {
	for ( const testData of payForOrderEur ) {
		testPaymentStatusOnPayForOrder( testData );
	}
} );
