/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import {
	testPaymentStatusOnClassicCheckout,
	testPaymentStatusOnCheckout,
	testPaymentStatusOnPayForOrder,
} from './_test-scenarios';
import {
	createShopOrder,
	creditCardDisabledMollieComponentsClassicCheckout,
	creditCardDisabledMollieComponentsCheckout,
	creditCardDisabledMollieComponentsPayForOrder,
} from './_test-data';
import { MollieSettings, shopSettings } from '../../../resources';

const testedApiMethod =
	( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) || 'payment';

test.beforeAll( async ( { utils, mollieApi } ) => {
	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'no',
	} );
} );

// Classic checkout page
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: true } );
	} );

	for ( const testData of creditCardDisabledMollieComponentsClassicCheckout ) {
		const order = createShopOrder( testData );
		testPaymentStatusOnClassicCheckout( testData.testId, order );
	}
} );

// Block checkout page
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: false } );
	} );

	for ( const testData of creditCardDisabledMollieComponentsCheckout ) {
		const order = createShopOrder( testData );

		// exclude tests for payment methods if not available for tested API
		const availableForApiMethods =
			order.payment.gateway.availableForApiMethods;
		if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
			continue;
		}

		testPaymentStatusOnCheckout( testData.testId, order );
	}

	for ( const testData of creditCardDisabledMollieComponentsPayForOrder ) {
		const order = createShopOrder( testData );

		// exclude tests for payment methods if not available for tested API
		const availableForApiMethods =
			order.payment.gateway.availableForApiMethods;
		if ( ! availableForApiMethods.includes( testedApiMethod ) ) {
			continue;
		}

		testPaymentStatusOnPayForOrder( testData.testId, order );
	}
} );

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'yes',
	} );
} );
