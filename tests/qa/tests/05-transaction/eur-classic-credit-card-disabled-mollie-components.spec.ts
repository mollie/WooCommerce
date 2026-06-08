/**
 * Internal dependencies
 */
import {
	testPaymentStatusOnClassicCheckout,
} from './_test-scenarios';
import {
	creditCardDisabledMollieComponentsClassicCheckout,
} from './_test-data';

for ( const testData of creditCardDisabledMollieComponentsClassicCheckout ) {
	testPaymentStatusOnClassicCheckout( testData );
}
