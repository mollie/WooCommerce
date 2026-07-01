/**
 * Verifies the e.originalEvent guard in the payment method change listener.
 *
 * The listener must trigger update_checkout only when the change originates
 * from genuine user interaction (e.originalEvent is set). Synthetic jQuery
 * .trigger() calls — used by Fastlane and WooCommerce init_payment_methods —
 * must not fire update_checkout, which prevents the infinite AJAX loop on
 * /?wc-ajax=update_order_review.
 */

const triggerSpy = jest.fn();
let capturedChangeHandler;

const mockBody = {
	on( event, selector, handler ) {
		if (
			event === 'change' &&
			selector === 'input[name="payment_method"]'
		) {
			capturedChangeHandler = handler;
		}
		return this;
	},
	trigger( event ) {
		triggerSpy( event );
		return this;
	},
};

const mockJQuery = function ( selectorOrFn ) {
	if ( typeof selectorOrFn === 'function' ) {
		// jQuery( function( $ ) { ... } ) — document ready, call immediately
		selectorOrFn( mockJQuery );
		return;
	}
	return mockBody;
};

beforeAll( () => {
	global.jQuery = mockJQuery;
	// surchargeData null keeps the IIFE in its early-return path after the
	// change handler is registered — exactly the guest-with-no-surcharge case
	global.surchargeData = null;
	require( '../gatewaySurcharge' );
} );

afterEach( () => {
	triggerSpy.mockClear();
} );

describe( 'gatewaySurcharge payment method change listener', () => {
	it( 'does not trigger update_checkout when change has no originalEvent (synthetic / programmatic)', () => {
		capturedChangeHandler( { originalEvent: undefined } );

		expect( triggerSpy ).not.toHaveBeenCalledWith( 'update_checkout' );
	} );

	it( 'triggers update_checkout exactly once when change has originalEvent (genuine user click)', () => {
		capturedChangeHandler( { originalEvent: new Event( 'change' ) } );

		expect( triggerSpy ).toHaveBeenCalledTimes( 1 );
		expect( triggerSpy ).toHaveBeenCalledWith( 'update_checkout' );
	} );

	it( 'does not trigger update_checkout across repeated programmatic changes (Fastlane loop scenario)', () => {
		for ( let i = 0; i < 10; i++ ) {
			capturedChangeHandler( { originalEvent: undefined } );
		}

		expect( triggerSpy ).not.toHaveBeenCalled();
	} );
} );
