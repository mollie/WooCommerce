// kb-active
/* eslint-env jest */
/**
 * expressReadiness — which of its three states the Express Component shows (REQ-B2, H1, H2; AC-9,
 * AC-13, AC-14).
 *
 * A pure function over what the block checkout already knows. It is a convenience, not a control:
 * every rule here is enforced again by POST express/session and POST express/order.
 *
 *   hidden   the cart cannot be paid this way (empty, or holds a subscription): render nothing
 *   blocked  the cart ships and the checkout form cannot price it yet: show the message and our own
 *            placeholder, request no session, mount nothing
 *   ready    request a session and mount the component
 *
 * Every wallet waits for the form, Apple Pay included, so readiness does not depend on the wallet.
 */
/**
 * Internal dependencies
 */
import { expressReadiness } from '../expressReadiness';

const READY = { status: 'ready' };
const BLOCKED = { status: 'blocked', reason: 'shipping_incomplete' };
const HIDDEN = { status: 'hidden' };

const completeAddress = {
	first_name: 'Ada',
	last_name: 'Lovelace',
	address_1: 'Main street 1',
	postcode: '1234',
	city: 'Town',
	country: 'LU',
};

const requiredForLu = [
	'first_name',
	'last_name',
	'address_1',
	'postcode',
	'city',
	'country',
];

/**
 * A cart that ships, with the shipping form complete and a rate chosen: ready.
 *
 * @param {Object} overrides Fields that differ from that cart.
 * @return {Object} The readiness input.
 */
const shippable = ( overrides = {} ) => ( {
	itemCount: 1,
	hasSubscription: false,
	needsShipping: true,
	shippingAddress: completeAddress,
	requiredShippingFields: requiredForLu,
	hasSelectedRate: true,
	hasShippingAmount: true,
	isCalculating: false,
	...overrides,
} );

/**
 * A cart with nothing to ship and an empty form.
 *
 * @param {Object} overrides Fields that differ from that cart.
 * @return {Object} The readiness input.
 */
const virtual = ( overrides = {} ) => ( {
	itemCount: 1,
	hasSubscription: false,
	needsShipping: false,
	shippingAddress: {},
	requiredShippingFields: requiredForLu,
	hasSelectedRate: false,
	hasShippingAmount: false,
	isCalculating: false,
	...overrides,
} );

describe( 'expressReadiness', () => {
	/**
	 * Scenario: the component shows the state the cart and the checkout form call for
	 *   Given what the block checkout knows about the cart and the shipping form
	 *   When readiness is decided
	 *   Then a cart with nothing to ship is ready whatever the form says
	 *   And a cart that ships is blocked while a required field is missing, no rate is selected
	 *       or the totals are still calculating, and ready once none of that holds
	 *   And an empty cart or one with a subscription is hidden, which the form cannot fix
	 */
	test.each( [
		[ 'no shipping needed, form empty', virtual(), READY ],
		[
			'no shipping needed, no rate',
			virtual( { hasSelectedRate: false } ),
			READY,
		],
		[
			'shipping needed, form complete and a rate selected',
			shippable(),
			READY,
		],
		[
			'shipping needed, a required field missing',
			shippable( {
				shippingAddress: { ...completeAddress, postcode: '' },
			} ),
			BLOCKED,
		],
		[
			'shipping needed, a required field only whitespace',
			shippable( {
				shippingAddress: { ...completeAddress, city: '   ' },
			} ),
			BLOCKED,
		],
		[
			'shipping needed, a required field absent',
			shippable( { shippingAddress: { country: 'LU' } } ),
			BLOCKED,
		],
		[
			'shipping needed, no rate selected',
			shippable( { hasSelectedRate: false } ),
			BLOCKED,
		],
		[
			'shipping needed, totals calculating',
			shippable( { isCalculating: true } ),
			BLOCKED,
		],
		[
			// A country on its own gets a rate out of WooCommerce, which once priced a session for an
			// address the shopper had not finished (owner, 2026-09-24).
			'shipping needed, a rate chosen but no shipping cost worked out yet',
			shippable( { hasShippingAmount: false } ),
			BLOCKED,
		],
		[
			'shipping needed, a field the country does not require is empty',
			shippable( {
				shippingAddress: { ...completeAddress, state: '' },
				requiredShippingFields: requiredForLu,
			} ),
			READY,
		],
		[ 'empty cart', virtual( { itemCount: 0 } ), HIDDEN ],
		[
			'subscription in the cart',
			virtual( { hasSubscription: true } ),
			HIDDEN,
		],
		[
			'empty cart that would also be blocked',
			shippable( { itemCount: 0, hasSelectedRate: false } ),
			HIDDEN,
		],
		[
			'subscription in a cart whose shipping is incomplete',
			shippable( { hasSubscription: true, shippingAddress: {} } ),
			HIDDEN,
		],
	] )( '%s', ( _name, input, expected ) => {
		expect( expressReadiness( input ) ).toEqual( expected );
	} );

	/**
	 * Scenario: deciding readiness changes nothing it is given
	 *   Given the block's cart and form data
	 *   When readiness is decided
	 *   Then the input is left exactly as it was
	 */
	test( 'does not mutate its input', () => {
		const input = shippable( {
			shippingAddress: { ...completeAddress, postcode: '' },
		} );
		const before = JSON.stringify( input );

		expressReadiness( input );

		expect( JSON.stringify( input ) ).toBe( before );
	} );
} );
