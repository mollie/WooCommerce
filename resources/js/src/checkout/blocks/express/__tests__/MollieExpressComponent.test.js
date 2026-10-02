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
import { postToStore, SESSION_ROUTE } from '../expressTransport';

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
jest.mock( '../MollieCheckoutManager', () => ( {
	MollieCheckoutManager: class {
		static isAvailable() {
			return true;
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
			messages: { unavailable: 'Unavailable' },
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
} );
