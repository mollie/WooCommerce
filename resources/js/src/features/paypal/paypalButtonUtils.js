/**
 * Module-level guard: prevents a second AJAX request if one is already in flight.
 * Reset to false on completion (success or error) so the button is usable again.
 *
 * @type {boolean}
 */
let isProcessing = false;

/**
 * Attach a click handler to the PayPal cart button.
 *
 * The `data-mollie-handler-attached` attribute prevents duplicate listeners from
 * accumulating. When WC's cart-totals AJAX refreshes the fragment it destroys the
 * old element (and its attribute), so re-attachment on the next `updated_cart_totals`
 * event is automatically permitted after a DOM refresh.
 *
 * @param {string} ajaxUrl WordPress admin-ajax URL.
 */
export const ensurePayPalButtonListenerAttached = ( ajaxUrl ) => {
	const button = document.getElementById( 'mollie-PayPal-button' );
	if ( ! button || button.dataset.mollieHandlerAttached ) {
		return;
	}
	const nonce = button.children[ 0 ].value;
	button.addEventListener( 'click', async ( evt ) => {
		evt.preventDefault();
		if ( isProcessing ) {
			return;
		}
		isProcessing = true;
		button.disabled = true;
		button.classList.add( 'buttonDisabled' );
		try {
			const params = new URLSearchParams( {
				action: 'mollie_paypal_create_order_cart',
				'mollie-payments-for-woocommerce_issuer_paypal_button': 'paypal',
				nonce,
			} );
			const response = await fetch( ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString(),
			} );
			const result = await response.json();
			if ( result.success === true ) {
				window.location.href = result.data.redirect;
			}
		} finally {
			isProcessing = false;
			button.disabled = false;
			button.classList.remove( 'buttonDisabled' );
		}
	} );
	button.dataset.mollieHandlerAttached = 'true';
};
