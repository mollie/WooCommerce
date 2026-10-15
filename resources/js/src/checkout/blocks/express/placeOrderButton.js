/**
 * While the Express Component is the selected payment method, its wallet buttons pay and the
 * checkout's Place order button is hidden. Blocks mounts a payment method's content only while it is
 * selected, so mounting hides the button and unmounting brings it back.
 *
 * The button is hidden by a rule keyed to a body class, not by changing the button: Blocks owns it
 * and may render it again at any time.
 */
/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';

export const BODY_CLASS = 'mollie-express-selected';
const STYLE_ID = 'mollie-express-place-order';
const BUTTON_SELECTOR = '.wc-block-components-checkout-place-order-button';

function addStyle() {
	if ( document.getElementById( STYLE_ID ) ) {
		return;
	}
	const style = document.createElement( 'style' );
	style.id = STYLE_ID;
	style.textContent = `body.${ BODY_CLASS } ${ BUTTON_SELECTOR } { display: none !important; }`;
	document.head.appendChild( style );
}

export function usePlaceOrderButtonHidden() {
	useEffect( () => {
		addStyle();
		document.body.classList.add( BODY_CLASS );

		return () => document.body.classList.remove( BODY_CLASS );
	}, [] );
}

/**
 * A checkout submitted anyway, by Enter in a field, is refused with a message of ours; the Store
 * API would refuse it too, with WooCommerce's text.
 *
 * @param {Object|undefined} eventRegistration Blocks' event registration; absent in the editor.
 * @param {Object|undefined} emitResponse      Blocks' response types.
 * @param {string}           message           Shown to the shopper.
 */
export function useRefusePlaceOrder(
	eventRegistration,
	emitResponse,
	message
) {
	const onPaymentSetup = eventRegistration?.onPaymentSetup;
	const errorType = emitResponse?.responseTypes?.ERROR ?? 'error';

	useEffect( () => {
		if ( typeof onPaymentSetup !== 'function' ) {
			return undefined;
		}

		return onPaymentSetup( () => ( { type: errorType, message } ) );
	}, [ onPaymentSetup, errorType, message ] );
}
