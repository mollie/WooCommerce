/* eslint-env jest */
/**
 * The checkout's Place order button while the Express Component is the selected payment method.
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
import {
	BODY_CLASS,
	usePlaceOrderButtonHidden,
	useRefusePlaceOrder,
} from '../placeOrderButton';

const MESSAGE = 'Please pay with one of the express checkout buttons.';

function SelectedExpressMethod( { eventRegistration, emitResponse } ) {
	usePlaceOrderButtonHidden();
	useRefusePlaceOrder( eventRegistration, emitResponse, MESSAGE );
	return null;
}

describe( 'the Place order button', () => {
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<button class="wc-block-components-checkout-place-order-button">Place order</button>'
		);
		root = createRoot( container );
	} );

	afterEach( async () => {
		await act( async () => root.unmount() );
		document.body.innerHTML = '';
		document.body.className = '';
	} );

	const select = async ( props = {} ) => {
		await act( async () => {
			root.render( createElement( SelectedExpressMethod, props ) );
		} );
	};

	const deselect = async () => {
		await act( async () => {
			root.render( null );
		} );
	};

	const buttonIsHidden = () =>
		window.getComputedStyle(
			document.querySelector(
				'.wc-block-components-checkout-place-order-button'
			)
		).display === 'none';

	/**
	 * Scenario: the shopper selects express checkout
	 *   Given the checkout shows the Place order button
	 *   When the shopper selects the Express Component
	 *   Then the Place order button is hidden
	 */
	it( 'is hidden while the Express Component is selected', async () => {
		expect( buttonIsHidden() ).toBe( false );

		await select();

		expect( document.body.classList.contains( BODY_CLASS ) ).toBe( true );
		expect( buttonIsHidden() ).toBe( true );
	} );

	/**
	 * Scenario: the shopper selects another payment method
	 *   Given the Express Component is selected and the Place order button is hidden
	 *   When the shopper selects another payment method
	 *   Then the Place order button is back
	 */
	it( 'comes back when another payment method is selected', async () => {
		await select();

		await deselect();

		expect( document.body.classList.contains( BODY_CLASS ) ).toBe( false );
		expect( buttonIsHidden() ).toBe( false );
	} );

	/**
	 * Scenario: the checkout is submitted anyway
	 *   Given the Express Component is selected
	 *   When the checkout is submitted, by Enter in a field
	 *   Then the payment setup fails with the message to use the express buttons
	 *   And after another payment method is selected, the check is gone
	 */
	it( 'refuses a checkout submitted while selected', async () => {
		const unsubscribe = jest.fn();
		const onPaymentSetup = jest.fn( () => unsubscribe );

		await select( {
			eventRegistration: { onPaymentSetup },
			emitResponse: { responseTypes: { ERROR: 'error' } },
		} );

		expect( onPaymentSetup ).toHaveBeenCalledTimes( 1 );
		const observer = onPaymentSetup.mock.calls[ 0 ][ 0 ];
		expect( observer() ).toEqual( { type: 'error', message: MESSAGE } );

		await deselect();

		expect( unsubscribe ).toHaveBeenCalledTimes( 1 );
	} );

	/**
	 * Scenario: the block editor renders the method without checkout events
	 *   Given there is no event registration
	 *   When the method is rendered
	 *   Then nothing fails
	 */
	it( 'renders without event registration', async () => {
		await expect( select() ).resolves.toBeUndefined();
	} );
} );
