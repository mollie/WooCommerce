/* eslint-env jest */
/**
 * MollieExpressComponent — how long an unused checkout keeps asking the store for sessions.
 *
 * A session about to expire is replaced once. A replacement nobody used either is not replaced: the
 * component goes idle, and asks again only when the shopper moves, types or returns to the tab.
 */
/**
 * WordPress dependencies
 */
import { createRoot, createElement } from '@wordpress/element';
/**
 * External dependencies
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- React's own test helper; @wordpress/element does not export it.
import { act } from 'react';
/**
 * Internal dependencies
 */
import MollieExpressComponent from '../MollieExpressComponent';
import { ORDER_ROUTE, postToStore, SESSION_ROUTE } from '../expressTransport';
import { formIsValid, showFormErrors, useFormIsValid } from '../checkoutForm';

const SESSION_LIFETIME_MS = 15 * 60 * 1000;

jest.mock( '@woocommerce/settings', () => ( { getSetting: () => ( {} ) } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: () => ( { status: 'ready', fingerprint: 'EUR|1000' } ),
	} ),
	{ virtual: true }
);
jest.mock( '../expressTransport', () => ( {
	ORDER_ROUTE: 'express/order',
	SESSION_ROUTE: 'express/session',
	postToStore: jest.fn(),
} ) );
jest.mock( '../checkoutForm', () => ( {
	formData: () => ( { customer_note: 'Ring twice' } ),
	formIsValid: jest.fn( () => true ),
	showFormErrors: jest.fn(),
	useFormIsValid: jest.fn( () => true ),
} ) );
// What Mollie calls when a wallet button is used.
const mockWallet = { submit: null };
jest.mock( '../MollieCheckoutManager', () => ( {
	MollieCheckoutManager: class {
		static isAvailable() {
			return true;
		}

		constructor( options, onSubmit ) {
			mockWallet.submit = onSubmit;
		}

		async mount() {
			return true;
		}

		async unmount() {}
	},
} ) );

const flush = async () => {
	for ( let i = 0; i < 10; i++ ) {
		await act( async () => {
			await Promise.resolve();
		} );
	}
};

describe( 'an idle checkout tab', () => {
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		jest.useFakeTimers();
		postToStore.mockReset();
		formIsValid.mockReturnValue( true );
		useFormIsValid.mockReturnValue( true );
		showFormErrors.mockClear();
		postToStore.mockImplementation( async () => ( {
			ok: true,
			data: {
				clientAccessToken: 'token',
				expiresAt: new Date(
					Date.now() + SESSION_LIFETIME_MS
				).toISOString(),
			},
		} ) );
		container = document.createElement( 'div' );
		document.body.appendChild( container );
	} );

	afterEach( async () => {
		await act( async () => root.unmount() );
		container.remove();
		jest.useRealTimers();
	} );

	const sessionRequests = () =>
		postToStore.mock.calls.filter(
			( [ , route ] ) => route === SESSION_ROUTE
		).length;

	const leaveTheTabOpenFor = async ( minutes ) => {
		for ( let minute = 0; minute < minutes; minute++ ) {
			await act( async () => {
				jest.advanceTimersByTime( 60 * 1000 );
			} );
			await flush();
		}
	};

	const openTheCheckout = async () => {
		const data = {
			locale: 'en_US',
			buttons: {},
			messages: {
				unavailable: 'Unavailable',
				checkForm: 'Check the form',
			},
		};
		await act( async () => {
			root = createRoot( container );
			root.render( createElement( MollieExpressComponent, { data } ) );
		} );
		await flush();
	};

	/**
	 * Scenario: a checkout nobody touches stops asking for sessions
	 *   Given the checkout is open and ready
	 *   When two hours pass and nothing is touched
	 *   Then the store was asked for the first session and its one replacement, and no more
	 */
	it( 'replaces an expiring session once', async () => {
		await openTheCheckout();

		await leaveTheTabOpenFor( 120 );

		expect( sessionRequests() ).toBe( 2 );
	} );

	/**
	 * Scenario: the shopper comes back to an idle checkout
	 *   Given a checkout that went idle after its replacement session expired unused
	 *   When the shopper moves the pointer
	 *   Then the store is asked for a session again, and that one is replaced once as well
	 */
	it( 'asks for a session again when the shopper is back', async () => {
		await openTheCheckout();
		await leaveTheTabOpenFor( 120 );

		await act( async () => {
			window.dispatchEvent( new Event( 'pointermove' ) );
		} );
		await flush();
		expect( sessionRequests() ).toBe( 3 );

		await leaveTheTabOpenFor( 120 );
		expect( sessionRequests() ).toBe( 4 );
	} );

	/**
	 * Scenario: Mollie cannot open a session
	 *   Given the shopper selected express checkout
	 *   When the store cannot open a session
	 *   Then the payment method's box says express checkout is not available
	 */
	it( 'says so when no session can be opened', async () => {
		postToStore.mockImplementation( async () => ( { ok: false } ) );

		await openTheCheckout();

		expect( container.textContent ).toContain( 'Unavailable' );
	} );

	/**
	 * Scenario: the store has not saved the address yet
	 *   Given the shopper just completed the address
	 *   When the store refuses the first session as shipping_incomplete
	 *   Then it is asked again, and the wallet buttons mount
	 */
	it( 'asks again while the store catches up with the address', async () => {
		const opened = postToStore.getMockImplementation();
		postToStore.mockImplementationOnce( async () => ( {
			ok: false,
			code: 'shipping_incomplete',
		} ) );
		postToStore.mockImplementation( opened );

		await openTheCheckout();
		await act( async () => {
			jest.advanceTimersByTime( 1000 );
		} );
		await flush();

		expect( sessionRequests() ).toBe( 2 );
		expect( container.textContent ).not.toContain( 'Unavailable' );
	} );

	/**
	 * Scenario: the store keeps refusing the address
	 *   Given the store refuses every session as shipping_incomplete
	 *   When the retries are used up
	 *   Then the payment method's box says express checkout is not available
	 */
	it( 'gives up when the store keeps refusing the address', async () => {
		postToStore.mockImplementation( async () => ( {
			ok: false,
			code: 'shipping_incomplete',
		} ) );

		await openTheCheckout();
		for ( const delay of [ 1000, 2000, 4000, 8000 ] ) {
			await act( async () => {
				jest.advanceTimersByTime( delay );
			} );
			await flush();
		}

		expect( sessionRequests() ).toBe( 4 );
		expect( container.textContent ).toContain( 'Unavailable' );
	} );

	const orderRequests = () =>
		postToStore.mock.calls.filter(
			( [ , route ] ) => route === ORDER_ROUTE
		);

	const useAWalletButton = async () => {
		const event = {
			defer: jest.fn(),
			resolve: jest.fn(),
			reject: jest.fn(),
		};
		await act( async () => {
			await mockWallet.submit( event );
		} );
		await flush();
		return event;
	};

	/**
	 * Scenario: the checkout form is not complete
	 *   Given the wallet buttons are mounted
	 *   And the form has an error, shown or not
	 *   Then the buttons are covered, and clicking them shows the form's errors
	 */
	it( 'covers the wallet buttons while the form is invalid', async () => {
		useFormIsValid.mockReturnValue( false );

		await openTheCheckout();
		const gate = container.querySelector(
			'.mollie-express-component__form-gate'
		);
		await act( async () => gate.click() );

		expect( gate.getAttribute( 'aria-label' ) ).toBe( 'Check the form' );
		expect( showFormErrors ).toHaveBeenCalledTimes( 1 );
	} );

	/**
	 * Scenario: the form became invalid while a wallet sheet was opening
	 *   Given the wallet buttons are mounted
	 *   When a wallet submits while the form has an error
	 *   Then the wallet is refused, the form's errors are shown, and no order is asked for
	 */
	it( 'refuses a wallet while the form is invalid', async () => {
		await openTheCheckout();
		formIsValid.mockReturnValue( false );

		const event = await useAWalletButton();

		expect( event.defer ).toHaveBeenCalled();
		expect( event.reject ).toHaveBeenCalledWith( 'Check the form' );
		expect( showFormErrors ).toHaveBeenCalledTimes( 1 );
		expect( orderRequests() ).toHaveLength( 0 );
	} );

	/**
	 * Scenario: a valid form goes along with the order
	 *   Given the wallet buttons are mounted and the form is valid
	 *   When a wallet submits
	 *   Then the order is asked for with the form, and the wallet may go on
	 */
	it( 'sends the form with the order', async () => {
		await openTheCheckout();
		postToStore.mockImplementation( async () => ( {
			ok: true,
			data: {},
		} ) );

		const event = await useAWalletButton();

		expect( orderRequests() ).toHaveLength( 1 );
		expect( orderRequests()[ 0 ][ 2 ] ).toEqual( {
			customer_note: 'Ring twice',
		} );
		expect( event.resolve ).toHaveBeenCalled();
	} );
} );
