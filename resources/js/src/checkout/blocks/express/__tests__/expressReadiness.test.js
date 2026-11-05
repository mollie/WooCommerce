/* eslint-env jest */
/**
 * expressReadiness — which of its two states the Express Component shows.
 *
 * A pure function over what the block checkout already knows. It is a convenience, not a control:
 * every rule here is enforced again by POST express/session and POST express/order.
 *
 *   hidden   the cart cannot be paid this way (empty, a subscription, or nothing to pay): render nothing
 *   ready    request a session and mount the component
 *
 * The wallet supplies the shipping address and the store prices the options for it, so the
 * shipping form never decides readiness (@REQ-H2).
 */
/**
 * Internal dependencies
 */
import { expressReadiness } from '../expressReadiness';

const READY = { status: 'ready' };
const HIDDEN = { status: 'hidden' };

/**
 * A cart, ready unless the overrides say otherwise.
 *
 * @param {Object} overrides Fields that differ from that cart.
 * @return {Object} The readiness input.
 */
const cart = ( overrides = {} ) => ( {
	itemCount: 1,
	hasSubscription: false,
	needsShipping: false,
	nothingToPay: false,
	...overrides,
} );

describe( 'expressReadiness', () => {
	/**
	 * Scenario: the component shows the state the cart calls for, whatever the shipping form holds
	 *   Given what the block checkout knows about the cart
	 *   When readiness is decided
	 *   Then a cart that ships is ready with an empty, half-filled or complete shipping form,
	 *       with no rate selected and while the totals are calculating
	 *   And an empty cart, one with a subscription, or one with nothing to pay is hidden
	 */
	test.each( [
		[ 'nothing to ship', cart(), READY ],
		[
			'shipping needed, empty form',
			cart( { needsShipping: true } ),
			READY,
		],
		[
			'shipping needed, a required field missing',
			cart( {
				needsShipping: true,
				shippingAddress: { country: 'LU', postcode: '' },
			} ),
			READY,
		],
		[
			'shipping needed, no rate selected',
			cart( { needsShipping: true, hasSelectedRate: false } ),
			READY,
		],
		[
			'shipping needed, no shipping cost worked out yet',
			cart( { needsShipping: true, hasShippingAmount: false } ),
			READY,
		],
		[
			'shipping needed, totals calculating',
			cart( { needsShipping: true, isCalculating: true } ),
			READY,
		],
		[ 'nothing to pay', cart( { nothingToPay: true } ), HIDDEN ],
		[
			'nothing to pay, shipping needed',
			cart( { needsShipping: true, nothingToPay: true } ),
			HIDDEN,
		],
		[ 'empty cart', cart( { itemCount: 0 } ), HIDDEN ],
		[
			'empty cart that ships',
			cart( { itemCount: 0, needsShipping: true } ),
			HIDDEN,
		],
		[
			'subscription in the cart',
			cart( { hasSubscription: true } ),
			HIDDEN,
		],
		[
			'subscription in a cart that ships',
			cart( { hasSubscription: true, needsShipping: true } ),
			HIDDEN,
		],
	] )( '@REQ-H2 %s', ( _name, input, expected ) => {
		expect( expressReadiness( input ) ).toEqual( expected );
	} );

	/**
	 * Scenario: deciding readiness changes nothing it is given
	 *   Given the block's cart data
	 *   When readiness is decided
	 *   Then the input is left exactly as it was
	 */
	test( 'does not mutate its input', () => {
		const input = cart( { needsShipping: true } );
		const before = JSON.stringify( input );

		expressReadiness( input );

		expect( JSON.stringify( input ) ).toBe( before );
	} );
} );
