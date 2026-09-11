/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	testPayPalExpressProduct,
	testPayPalExpressCart,
	testPayPalExpressCheckout,
} from './_test-scenarios';
import {
	payPalExpressProduct,
	payPalExpressCart,
	payPalExpressCheckout,
} from './_test-data';

// Each describe block enables only the setting it's testing (and explicitly
// disables the other two) so the tests also double as regression coverage
// for the cart/product/checkout display settings not leaking into each other.

// Product page
test.describe( () => {
	test.beforeAll( async ( { mollieApi } ) => {
		await mollieApi.updateMollieGateway( 'paypal', {
			mollie_paypal_button_enabled_product: 'yes',
			mollie_paypal_button_enabled_cart: 'no',
			mollie_paypal_button_enabled_checkout: 'no',
			mollie_paypal_button_minimum_amount: '0',
		} );
	} );

	for ( const testData of payPalExpressProduct ) {
		testPayPalExpressProduct( testData );
	}
} );

// Cart page (Block cart Express Payment area)
test.describe( () => {
	test.beforeAll( async ( { mollieApi } ) => {
		await mollieApi.updateMollieGateway( 'paypal', {
			mollie_paypal_button_enabled_cart: 'yes',
			mollie_paypal_button_enabled_product: 'no',
			mollie_paypal_button_enabled_checkout: 'no',
			mollie_paypal_button_minimum_amount: '0',
		} );
	} );

	for ( const testData of payPalExpressCart ) {
		testPayPalExpressCart( testData );
	}
} );

// Checkout page (Block checkout Express Payment area)
test.describe( () => {
	test.beforeAll( async ( { mollieApi } ) => {
		await mollieApi.updateMollieGateway( 'paypal', {
			mollie_paypal_button_enabled_checkout: 'yes',
			mollie_paypal_button_enabled_cart: 'no',
			mollie_paypal_button_enabled_product: 'no',
			mollie_paypal_button_minimum_amount: '0',
		} );
	} );

	for ( const testData of payPalExpressCheckout ) {
		testPayPalExpressCheckout( testData );
	}
} );

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'paypal', {
		mollie_paypal_button_enabled_product: 'no',
		mollie_paypal_button_enabled_cart: 'no',
		mollie_paypal_button_enabled_checkout: 'no',
	} );
} );
