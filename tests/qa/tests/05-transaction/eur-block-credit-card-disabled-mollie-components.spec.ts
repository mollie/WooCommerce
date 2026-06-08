/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import {
	creditCardDisabledMollieComponentsCheckout,
	creditCardDisabledMollieComponentsPayForOrder,
} from './_test-data';

for ( const testData of creditCardDisabledMollieComponentsCheckout ) {
	testPaymentStatusOnCheckout( testData );
}

for ( const testData of creditCardDisabledMollieComponentsPayForOrder ) {
	testPaymentStatusOnPayForOrder( testData );
}

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'yes',
	} );
} );
