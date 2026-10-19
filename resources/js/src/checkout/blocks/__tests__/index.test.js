/* eslint-env jest */
/**
 * The block checkout entry: a failing Express Component registration must not take the rest of
 * the Mollie block checkout down with it. The store listeners existed before Express did.
 */

jest.mock(
	'@wordpress/data',
	() => ( {
		select: () => ( { getIsOrderPayPage: () => false } ),
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/hooks', () => ( { addFilter: jest.fn() } ), {
	virtual: true,
} );
jest.mock( '@woocommerce/settings', () => ( { getSetting: jest.fn() } ), {
	virtual: true,
} );
jest.mock( '../store', () => ( { MOLLIE_STORE_KEY: 'mollie' } ) );
jest.mock( '../store/storeListeners', () => ( {
	initializeMollieStoreListeners: jest.fn(),
} ) );
jest.mock( '../components/PaymentMethodContentRenderer', () => ( {} ) );
jest.mock( '../../../shared/utils/applePayUtils', () => ( {
	ApplePayUtils: { GATEWAY_NAME: 'mollie_wc_gateway_applepay' },
} ) );
jest.mock(
	'../components/expressPayments/ApplePayButtonComponent',
	() => () => null
);
jest.mock(
	'../components/expressPayments/ApplePayButtonEditorComponent',
	() => () => null
);
jest.mock( '../../../shared/utils/paymentUtils', () => ( {
	isEditorContext: () => false,
} ) );
jest.mock(
	'../components/expressPayments/PayPalButtonComponent',
	() => () => null
);
jest.mock(
	'../components/expressPayments/PayPalButtonEditorComponent',
	() => () => null
);
jest.mock( '../../../shared/utils/paypalUtils', () => ( {
	PayPalUtils: { GATEWAY_NAME: 'mollie_wc_gateway_paypal' },
} ) );
jest.mock( '../registration/contextBuilder', () => ( {
	buildRegistrationContext: () => ( {} ),
} ) );
jest.mock( '../express/registerExpressComponent', () => ( {
	registerExpressComponent: jest.fn(),
} ) );

const loadEntry = () => {
	let modules;
	jest.isolateModules( () => {
		const {
			registerExpressComponent,
		} = require( '../express/registerExpressComponent' );
		registerExpressComponent.mockImplementation( () => {
			throw new Error( 'The registry refused the Express Component.' );
		} );
		require( '../index' );
		modules = {
			listeners: require( '../store/storeListeners' ),
			registerExpressComponent,
		};
	} );

	return modules;
};

describe( 'block checkout entry', () => {
	beforeEach( () => {
		global.wc = {};
		window.inpsydeGateways = [];
	} );

	afterEach( () => {
		delete global.wc;
		delete window.inpsydeGateways;
	} );

	it( 'starts the store listeners when the Express Component cannot be registered', () => {
		const { listeners, registerExpressComponent } = loadEntry();

		expect( registerExpressComponent ).toHaveBeenCalledTimes( 1 );
		expect(
			listeners.initializeMollieStoreListeners
		).toHaveBeenCalledTimes( 1 );
		expect( console ).toHaveErrored();
	} );
} );
