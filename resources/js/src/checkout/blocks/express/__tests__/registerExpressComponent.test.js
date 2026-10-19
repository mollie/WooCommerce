/* eslint-env jest */
/**
 * Where Blocks shows the Express Component: in the payment step, after the address and the shipping.
 */
/**
 * Internal dependencies
 */
import {
	EXPRESS_METHOD_NAME,
	registerExpressComponent,
} from '../registerExpressComponent';

jest.mock(
	'@woocommerce/blocks-registry',
	() => ( {
		registerPaymentMethod: jest.fn(),
		registerExpressPaymentMethod: jest.fn(),
	} ),
	{ virtual: true }
);
jest.mock( '../MollieExpressComponent', () => ( {
	__esModule: true,
	default: () => null,
	canMakePayment: () => true,
} ) );
jest.mock( '../MollieExpressEditorComponent', () => ( {
	__esModule: true,
	default: () => null,
} ) );

/**
 * External dependencies
 */
const {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} = require( '@woocommerce/blocks-registry' );

describe( 'registerExpressComponent', () => {
	beforeEach( () => {
		registerPaymentMethod.mockReset();
		registerExpressPaymentMethod.mockReset();
		delete window.mollieExpressData;
	} );

	/**
	 * Scenario: a block checkout Express owns
	 *   Given the store printed the Express data
	 *   When the block checkout registers its payment methods
	 *   Then the Express Component is a regular payment method, not an express one
	 */
	it( 'registers a regular payment method', () => {
		window.mollieExpressData = {
			messages: { placeholder: 'Express checkout' },
		};

		registerExpressComponent();

		expect( registerExpressPaymentMethod ).not.toHaveBeenCalled();
		expect( registerPaymentMethod ).toHaveBeenCalledTimes( 1 );
		const [ config ] = registerPaymentMethod.mock.calls[ 0 ];
		expect( config.name ).toBe( EXPRESS_METHOD_NAME );
		expect( config.ariaLabel ).toBe( 'Express checkout' );
		expect( config.supports ).toEqual( {
			features: [ 'products' ],
			showSavedCards: false,
			showSaveOption: false,
		} );
	} );

	/**
	 * Scenario: a checkout Express does not own
	 *   Given the store printed no Express data
	 *   When the block checkout registers its payment methods
	 *   Then nothing is registered
	 */
	it( 'registers nothing without the Express data', () => {
		registerExpressComponent();

		expect( registerPaymentMethod ).not.toHaveBeenCalled();
		expect( registerExpressPaymentMethod ).not.toHaveBeenCalled();
	} );
} );
