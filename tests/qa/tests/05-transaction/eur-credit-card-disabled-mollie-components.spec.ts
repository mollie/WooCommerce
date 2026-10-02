/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPaymentStatusOnClassicCheckout,
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import {
	creditCardDisabledMollieComponentsClassicCheckout,
	creditCardDisabledMollieComponentsCheckout,
	creditCardDisabledMollieComponentsPayForOrder,
} from './_test-data';

// Classic checkout page
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: true } );
	} );

	for ( const testData of creditCardDisabledMollieComponentsClassicCheckout ) {
		testPaymentStatusOnClassicCheckout( testData );
	}
} );

// Block checkout page
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: false } );
	} );

	for ( const testData of creditCardDisabledMollieComponentsCheckout ) {
		testPaymentStatusOnCheckout( testData );
	}

	for ( const testData of creditCardDisabledMollieComponentsPayForOrder ) {
		testPaymentStatusOnPayForOrder( testData );
	}
} );

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'yes',
	} );
} );
