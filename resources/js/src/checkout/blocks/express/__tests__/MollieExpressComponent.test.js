/* eslint-env jest */
/**
 * MollieExpressComponent — how long an unused checkout keeps asking the store for sessions, and
 * what the express area shows for the cart the store holds.
 *
 * A session about to expire is replaced once. A replacement nobody used either is not replaced: the
 * component goes idle, and asks again only when the shopper moves, types or returns to the tab.
 * While the shipping form cannot price the cart the area shows nothing; a cart that ships says where.
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
/**
 * The cart store as the checkout holds it. By default a cart with nothing to ship, ready to pay.
 *
 * @param {Object}  cart
 * @param {boolean} cart.needsShipping Something in the cart is shipped.
 * @param {Object}  cart.address       The shipping fields as the form holds them.
 * @param {boolean} cart.rateSelected  The shopper's rate is selected and priced.
 * @param {string}  cart.rateName      The selected rate's name.
 * @return {Object} What the component's select() reads.
 */
const mockCartStore = ( {
	needsShipping = false,
	address = {},
	rateSelected = false,
	rateName = 'Flat rate',
} = {} ) => ( {
	getCartData: () => ( { items: [ { key: 'line-1', quantity: 1 } ] } ),
	getCartTotals: () => ( {
		currency_code: 'EUR',
		total_price: '1000',
		total_shipping: needsShipping && rateSelected ? '500' : null,
	} ),
	getCustomerData: () => ( { shippingAddress: address } ),
	getShippingRates: () =>
		needsShipping
			? [
					{
						package_id: 0,
						shipping_rates: [
							{
								rate_id: 'flat_rate:1',
								name: rateName,
								selected: rateSelected,
							},
						],
					},
			  ]
			: [],
	getNeedsShipping: () => needsShipping,
	isCustomerDataUpdating: () => false,
	isShippingRateBeingSelected: () => false,
} );
let mockCart = mockCartStore();

jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: ( mapSelect ) => mapSelect( () => mockCart ),
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

describe( 'what the express area shows', () => {
	const SETTLE_MS = 1000;
	const ADDRESS = {
		first_name: 'Ada',
		last_name: 'Lovelace',
		address_1: 'Rue du Fort 12',
		postcode: '1234',
		city: 'Luxembourg',
		country: 'LU',
	};
	const data = {
		locale: 'en_US',
		buttons: {},
		messages: {
			unavailable: 'Unavailable',
			shipsTo: 'Ships to',
			change: 'Change',
		},
	};
	let container;
	let root;
	let shippingFields;

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
		// The block checkout's own shipping address fields, which Change moves the shopper to.
		shippingFields = document.createElement( 'div' );
		shippingFields.id = 'shipping-fields';
		shippingFields.innerHTML = '<input id="shipping-address_1" />';
		document.body.appendChild( shippingFields );
		window.Element.prototype.scrollIntoView = jest.fn();
		container = document.createElement( 'div' );
		document.body.appendChild( container );
	} );

	afterEach( async () => {
		await act( async () => root.unmount() );
		container.remove();
		shippingFields.remove();
		mockCart = mockCartStore();
		jest.useRealTimers();
	} );

	const sessionRequests = () =>
		postToStore.mock.calls.filter(
			( [ , route ] ) => route === SESSION_ROUTE
		).length;

	const render = async () => {
		await act( async () => {
			root = root ?? createRoot( container );
			root.render( createElement( MollieExpressComponent, { data } ) );
		} );
		await flush();
	};

	const openTheCheckout = async ( cart ) => {
		root = null;
		mockCart = mockCartStore( cart );
		await render();
	};

	const theShopperChangesTheForm = async ( cart ) => {
		mockCart = mockCartStore( cart );
		await render();
		await act( async () => {
			jest.advanceTimersByTime( SETTLE_MS );
		} );
		await flush();
	};

	const changeButton = () =>
		Array.from( container.querySelectorAll( 'button' ) ).find(
			( button ) => button.textContent.trim() === 'Change'
		);

	const text = ( element ) =>
		element.textContent.replace( /\s+/g, ' ' ).trim();

	/**
	 * Scenario: a cart the shipping form cannot price yet shows nothing
	 *   Given a cart that ships, and a shipping form with no rate chosen
	 *   When the checkout renders the express area
	 *   Then nothing is rendered: no placeholder, no message
	 *   And the store is not asked for a session
	 */
	it( 'renders nothing while the shipping form is incomplete', async () => {
		await openTheCheckout( {
			needsShipping: true,
			address: { country: 'LU' },
			rateSelected: false,
		} );

		expect( container.innerHTML ).toBe( '' );
		expect(
			document.querySelector( '.mollie-express-placeholder' )
		).toBeNull();
		expect( sessionRequests() ).toBe( 0 );
	} );

	/**
	 * Scenario: the shopper edits the address on an owned checkout
	 *   Given a checkout that loaded with a complete shipping address and a chosen rate
	 *   When the shopper's edit leaves the form without a priced rate
	 *   Then the component unmounts and the area renders nothing
	 *   When the form is complete again, for another address
	 *   Then the component mounts again with a new session
	 */
	it( 'remounts with a new session once the form is complete again', async () => {
		await openTheCheckout( {
			needsShipping: true,
			address: ADDRESS,
			rateSelected: true,
		} );
		expect( sessionRequests() ).toBe( 1 );
		expect( container.innerHTML ).not.toBe( '' );

		await theShopperChangesTheForm( {
			needsShipping: true,
			address: { ...ADDRESS, postcode: '' },
			rateSelected: false,
		} );
		expect( container.innerHTML ).toBe( '' );

		await theShopperChangesTheForm( {
			needsShipping: true,
			address: { ...ADDRESS, postcode: '4321', city: 'Esch' },
			rateSelected: true,
		} );
		expect( container.innerHTML ).not.toBe( '' );
		expect( sessionRequests() ).toBe( 2 );
	} );

	/**
	 * Scenario: a cart that ships says where it ships and lets the shopper change it
	 *   Given a cart that ships, with a complete shipping address and a chosen rate
	 *   When the checkout renders the express area
	 *   Then one line reads "Ships to <address_1>, <postcode> <city> · <rate>"
	 *   And its Change button moves focus to the shipping address fields
	 */
	it( 'shows where the order ships and lets the shopper change it', async () => {
		await openTheCheckout( {
			needsShipping: true,
			address: ADDRESS,
			rateSelected: true,
			rateName: 'Flat rate',
		} );

		const change = changeButton();
		expect( change ).toBeDefined();
		expect( text( change.parentElement ) ).toContain(
			'Ships to Rue du Fort 12, 1234 Luxembourg · Flat rate'
		);

		await act( async () => change.click() );

		expect( document.activeElement ).toBe(
			document.getElementById( 'shipping-address_1' )
		);
	} );

	/**
	 * Scenario: a cart with nothing to ship has no shipping line
	 *   Given a cart with nothing to ship
	 *   When the checkout renders the express area
	 *   Then the component mounts, with no "Ships to" line and no Change button
	 */
	it( 'shows no shipping line for a cart that does not ship', async () => {
		await openTheCheckout( { needsShipping: false } );

		expect( sessionRequests() ).toBe( 1 );
		expect( container.textContent ).not.toContain( 'Ships to' );
		expect( changeButton() ).toBeUndefined();
	} );
} );
