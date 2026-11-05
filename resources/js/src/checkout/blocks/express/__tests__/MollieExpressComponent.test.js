/* eslint-env jest */
/**
 * MollieExpressComponent — how long an unused checkout keeps asking the store for sessions, and
 * what the express area shows for the cart the store holds.
 *
 * A session about to expire is replaced once. A replacement nobody used either is not replaced: the
 * component goes idle, and asks again only when the shopper moves, types or returns to the tab.
 * A cart that ships is offered whatever the shipping form holds: the wallet gives the address.
 * A cart the store opened no session for is not offered to Blocks, so its express frame goes too.
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
import MollieExpressComponent, {
	canMakePayment,
} from '../MollieExpressComponent';
import { ORDER_ROUTE, postToStore, SESSION_ROUTE } from '../expressTransport';

const SESSION_LIFETIME_MS = 15 * 60 * 1000;

/** What the block checkout sends through getSetting(); nothing by default. */
let mockSettings = {};
jest.mock(
	'@woocommerce/settings',
	() => ( {
		getSetting: ( name, fallback ) => mockSettings[ name ] ?? fallback,
	} ),
	{ virtual: true }
);
/**
 * The cart store as the checkout holds it. By default a cart with nothing to ship, ready to pay.
 *
 * @param {Object}  cart
 * @param {boolean} cart.needsShipping Something in the cart is shipped.
 * @param {Object}  cart.address       The shipping fields as the form holds them.
 * @param {boolean} cart.rateSelected  The shopper's rate is selected and priced.
 * @param {string}  cart.rateName      The selected rate's name.
 * @param {number}  cart.quantity      How many of the one line.
 * @return {Object} What the component's select() reads.
 */
const mockCartStore = ( {
	needsShipping = false,
	address = {},
	rateSelected = false,
	rateName = 'Flat rate',
	quantity = 1,
} = {} ) => ( {
	getCartData: () => ( { items: [ { key: 'line-1', quantity } ] } ),
	getCartTotals: () => ( {
		currency_code: 'EUR',
		// Blocks' total includes the shipping once a rate is selected; the session is priced without it.
		total_price: needsShipping && rateSelected ? '1605' : '1000',
		total_shipping: needsShipping && rateSelected ? '500' : null,
		total_shipping_tax: needsShipping && rateSelected ? '105' : null,
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
/** Blocks' payment store: asked to run canMakePayment again. */
const mockPaymentStore = {
	__internalUpdateAvailablePaymentMethods: jest.fn(),
};

jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: ( mapSelect ) => mapSelect( () => mockCart ),
		select: () => mockCart,
		dispatch: ( store ) =>
			store === 'wc/store/payment' ? mockPaymentStore : {},
	} ),
	{ virtual: true }
);
jest.mock( '../expressTransport', () => ( {
	ORDER_ROUTE: 'express/order',
	SESSION_ROUTE: 'express/session',
	postToStore: jest.fn(),
} ) );
/** The component's submit handler, as the manager received it: the wallet calls it on approval. */
let mockSubmit = null;
jest.mock( '../MollieCheckoutManager', () => ( {
	MollieCheckoutManager: class {
		constructor( options, onSubmit ) {
			mockSubmit = onSubmit;
		}

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
		},
	};
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
		mockCart = mockCartStore();
		mockSettings = {};
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

	/**
	 * Scenario: a cart that ships is offered whatever the shipping form holds
	 *   Given a cart that ships, with an empty shipping form and no rate chosen
	 *   When the checkout renders the express area
	 *   Then the component is mounted and the store is asked for one session
	 *
	 * The wallet supplies the address; the store prices the shipping options for it.
	 */
	it( '@REQ-H2 mounts for a cart that ships whatever the form holds', async () => {
		await openTheCheckout( {
			needsShipping: true,
			address: { country: 'LU' },
			rateSelected: false,
		} );

		expect( container.innerHTML ).not.toBe( '' );
		expect( sessionRequests() ).toBe( 1 );
	} );

	/**
	 * Scenario: the shopper edits the shipping form of an owned checkout
	 *   Given a checkout that loaded with a cart that ships and a complete form
	 *   When the shopper clears a field, or picks another address and rate
	 *   Then the component stays mounted and the store is not asked for another session
	 *
	 * The session is priced without shipping, so a change of the form does not change its price.
	 */
	it( '@REQ-H2 keeps its session when the form or the rate changes', async () => {
		await openTheCheckout( {
			needsShipping: true,
			address: ADDRESS,
			rateSelected: true,
		} );
		expect( sessionRequests() ).toBe( 1 );

		await theShopperChangesTheForm( {
			needsShipping: true,
			address: { ...ADDRESS, postcode: '' },
			rateSelected: false,
		} );
		expect( container.innerHTML ).not.toBe( '' );

		await theShopperChangesTheForm( {
			needsShipping: true,
			address: { ...ADDRESS, postcode: '4321', city: 'Esch' },
			rateSelected: true,
			rateName: 'Express',
		} );
		expect( container.innerHTML ).not.toBe( '' );
		expect( sessionRequests() ).toBe( 1 );
	} );

	/**
	 * Scenario: Blocks keeps its express frame for a cart that ships, whatever the form holds
	 *   Given a checkout whose cart ships
	 *   When the shipping form is empty, a required field is cleared, or the form is complete
	 *   Then canMakePayment says yes each time
	 */
	it( '@REQ-H2 is offered to Blocks whatever the shipping form holds', async () => {
		const blocksArgs = () => ( {
			cart: { cartItems: mockCart.getCartData().items },
		} );
		mockSettings = { defaultFields: { postcode: { required: true } } };
		const priced = {
			needsShipping: true,
			address: ADDRESS,
			rateSelected: true,
		};

		for ( const form of [
			{ ...priced, address: {}, rateSelected: false },
			{ ...priced, address: { ...ADDRESS, postcode: '' } },
			priced,
		] ) {
			mockCart = mockCartStore( form );
			expect( canMakePayment( blocksArgs() ) ).toBe( true );
		}
	} );
} );

describe( 'a cart the store opened no session for', () => {
	const SETTLE_MS = 1000;
	const data = {
		locale: 'en_US',
		buttons: {},
		messages: { unavailable: 'Unavailable' },
	};
	// A cart of its own: what the store refused here must not leak into the other scenarios.
	const REFUSED = { quantity: 7 };
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		jest.useFakeTimers();
		mockPaymentStore.__internalUpdateAvailablePaymentMethods.mockReset();
		postToStore.mockReset();
		postToStore.mockImplementation( async () => ( {
			ok: false,
			code: 'budget_exhausted',
			message: '',
		} ) );
		container = document.createElement( 'div' );
		document.body.appendChild( container );
	} );

	afterEach( async () => {
		await act( async () => root.unmount() );
		container.remove();
		mockCart = mockCartStore();
		jest.useRealTimers();
	} );

	const blocksArgs = () => ( {
		cart: { cartItems: mockCart.getCartData().items },
	} );

	const render = async () => {
		await act( async () => {
			root = root ?? createRoot( container );
			root.render( createElement( MollieExpressComponent, { data } ) );
		} );
		await flush();
	};

	/**
	 * Scenario: the store refuses the session, so Blocks drops the express area
	 *   Given a checkout whose cart Blocks offered to the express area
	 *   When the store answers the session request with a refusal
	 *   Then Blocks is asked to run canMakePayment again
	 *   And canMakePayment says no for that cart
	 */
	it( 'is no longer offered to Blocks, and Blocks is asked again', async () => {
		root = null;
		mockCart = mockCartStore( REFUSED );
		expect( canMakePayment( blocksArgs() ) ).toBe( true );

		await render();

		expect(
			mockPaymentStore.__internalUpdateAvailablePaymentMethods
		).toHaveBeenCalledTimes( 1 );
		expect( canMakePayment( blocksArgs() ) ).toBe( false );
	} );

	/**
	 * Scenario: a changed cart is offered again
	 *   Given a cart the store opened no session for
	 *   When the shopper changes the quantity
	 *   Then canMakePayment says yes, and the store is asked for a session for the new cart
	 */
	it( 'is offered again once the cart changes', async () => {
		root = null;
		mockCart = mockCartStore( REFUSED );
		await render();
		expect( canMakePayment( blocksArgs() ) ).toBe( false );

		mockCart = mockCartStore( { quantity: REFUSED.quantity + 1 } );
		expect( canMakePayment( blocksArgs() ) ).toBe( true );
		await render();
		await act( async () => {
			jest.advanceTimersByTime( SETTLE_MS );
		} );
		await flush();

		expect(
			postToStore.mock.calls.filter(
				( [ , route ] ) => route === SESSION_ROUTE
			)
		).toHaveLength( 2 );
	} );
} );

describe( 'a submit', () => {
	const data = {
		locale: 'en_US',
		buttons: {},
		messages: {
			unavailable: 'Unavailable',
			reload: 'Please reload the page and try again.',
		},
	};
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		container = document.createElement( 'div' );
		document.body.appendChild( container );
	} );

	afterEach( async () => {
		await act( async () => root.unmount() );
		container.remove();
	} );

	const theStoreAnswersTheOrderWith = ( answer ) => {
		postToStore.mockReset();
		postToStore.mockImplementation( async ( _data, route ) =>
			route === ORDER_ROUTE
				? answer
				: {
						ok: true,
						data: {
							clientAccessToken: 'token',
							expiresAt: new Date(
								Date.now() + SESSION_LIFETIME_MS
							).toISOString(),
						},
				  }
		);
	};

	const theShopperApprovesInTheWallet = async () => {
		await act( async () => {
			root = createRoot( container );
			root.render( createElement( MollieExpressComponent, { data } ) );
		} );
		await flush();
		const event = {
			defer: jest.fn(),
			resolve: jest.fn(),
			reject: jest.fn(),
		};
		await act( async () => {
			await mockSubmit( event );
		} );
		await flush();
		return event;
	};

	const notice = () =>
		container.querySelector( '.mollie-express-component__notice' )
			?.textContent;

	/**
	 * Scenario: the shopper logged in in another tab
	 *   Given a checkout opened as a guest
	 *   When WordPress refuses the order request because the login changed (rest_cookie_invalid_nonce)
	 *   Then the wallet is rejected with the reload message, and the area shows it
	 *   And WordPress's own text is shown nowhere
	 */
	it( 'tells the shopper to reload when the login changed in another tab', async () => {
		theStoreAnswersTheOrderWith( {
			ok: false,
			code: 'rest_cookie_invalid_nonce',
			message: 'Cookie check failed',
		} );

		const event = await theShopperApprovesInTheWallet();

		expect( event.reject ).toHaveBeenCalledWith(
			'Please reload the page and try again.'
		);
		expect( notice() ).toBe( 'Please reload the page and try again.' );
		expect( container.textContent ).not.toContain( 'Cookie check failed' );
	} );

	/**
	 * Scenario: the store refuses with its own message
	 *   When the order route refuses with a code and message of its own
	 *   Then that message is the one the shopper sees
	 */
	it( 'shows the store’s own message for the store’s own refusals', async () => {
		theStoreAnswersTheOrderWith( {
			ok: false,
			code: 'cart_invalid',
			message: 'This item just sold out.',
		} );

		const event = await theShopperApprovesInTheWallet();

		expect( event.reject ).toHaveBeenCalledWith(
			'This item just sold out.'
		);
		expect( notice() ).toBe( 'This item just sold out.' );
	} );

	/**
	 * Scenario: the submit names the session the wallet shows
	 *   Given a component mounted with the session the store gave this tab
	 *   When the shopper approves in the wallet
	 *   Then the order request carries that session's token
	 */
	it( 'submits the token of the session it mounted', async () => {
		theStoreAnswersTheOrderWith( { ok: true, data: { ok: true } } );

		await theShopperApprovesInTheWallet();

		const orderCalls = postToStore.mock.calls.filter(
			( [ , route ] ) => route === ORDER_ROUTE
		);
		expect( orderCalls ).toHaveLength( 1 );
		expect( orderCalls[ 0 ][ 2 ] ).toEqual( { token: 'token' } );
	} );

	/**
	 * Scenario: another tab replaced the session this tab shows
	 *   When the order route refuses with session_replaced
	 *   Then the wallet is rejected with the store's message
	 *   And the component asks the store for a session again
	 */
	it( 'asks for a session again when another tab replaced this one', async () => {
		theStoreAnswersTheOrderWith( {
			ok: false,
			code: 'session_replaced',
			message: 'Your order changed after express checkout was started.',
		} );

		const event = await theShopperApprovesInTheWallet();

		expect( event.reject ).toHaveBeenCalledWith(
			'Your order changed after express checkout was started.'
		);
		expect(
			postToStore.mock.calls.filter(
				( [ , route ] ) => route === SESSION_ROUTE
			)
		).toHaveLength( 2 );
	} );
} );
