import { dispatch } from '@wordpress/data';
// Register the Mollie store so it is available in the classic cart bundle.
import { MOLLIE_STORE_KEY } from '../../checkout/blocks/store/constants';
import '../../checkout/blocks/store/index';

/**
 * Attach a click handler to the PayPal cart button.
 *
 * The `data-mollie-handler-attached` attribute is used as a deduplication guard:
 * WooCommerce's cart-totals AJAX replaces the cart fragment on every update,
 * destroying the old element along with its attribute, so re-attachment on the
 * next `updated_cart_totals` event is automatically permitted after a DOM refresh.
 * This prevents duplicate listeners from accumulating when both the `setTimeout`
 * and `updated_cart_totals` paths fire during a normal page load.
 *
 * @param {string} ajaxUrl WordPress admin-ajax URL.
 */
export const ensurePayPalButtonListenerAttached = ( ajaxUrl ) => {
	const button = document.getElementById( 'mollie-PayPal-button' );
	if ( ! button || button.dataset.mollieHandlerAttached ) {
		return;
	}
	const nonce = button.children[ 0 ].value;
	button.addEventListener( 'click', ( evt ) => {
		evt.preventDefault();
		dispatch( MOLLIE_STORE_KEY ).createPayPalCartOrder( ajaxUrl, nonce );
	} );
	button.dataset.mollieHandlerAttached = 'true';
};
