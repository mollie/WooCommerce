/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import {
	testPaymentStatusOnClassicCheckout,
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './.test-scenarios';
import {
	createShopOrder,
	creditCardMollieComponentsClassicCheckout,
	creditCardMollieComponentsCheckout,
	creditCardMollieComponentsPayForOrder,
} from './.test-data';
import { shopSettings } from '../../../resources';

test.beforeAll( async ( { utils, mollieApi } ) => {
	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'yes',
	} );
} );

// Classic checkout page
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
			await utils.configureStore( { classicPages: true } );
	} );

	for ( const testData of creditCardMollieComponentsClassicCheckout ) {
		const order = createShopOrder( testData );
		testPaymentStatusOnClassicCheckout( testData.testId, order );
	}
} );

// Block checkout page
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
			await utils.configureStore( { classicPages: false } );
	} );

	for ( const testData of creditCardMollieComponentsCheckout ) {
		const order = createShopOrder( testData );
		testPaymentStatusOnCheckout( testData.testId, order );
	}

	for ( const testData of creditCardMollieComponentsPayForOrder ) {
		const order = createShopOrder( testData );
		testPaymentStatusOnPayForOrder( testData.testId, order );
	}
} );

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'no',
	} );
} );
