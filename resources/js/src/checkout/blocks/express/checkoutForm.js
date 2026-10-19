/**
 * The checkout form around the Express Component. Place order checks it in the browser and sends it
 * to the store; the wallet buttons do both as well, so paying with them skips nothing: not the terms,
 * not a required field, not the order note.
 *
 * Blocks registers every field's error as soon as the field renders, hidden until the shopper
 * touches it or Place order is pressed. A form with only hidden errors is not valid either.
 */
/**
 * WordPress dependencies
 */
import { dispatch, select, useSelect } from '@wordpress/data';

const VALIDATION_STORE = 'wc/store/validation';
const CHECKOUT_STORE = 'wc/store/checkout';
const CART_STORE = 'wc/store/cart';
const FIRST_ERROR = '.wc-block-checkout .has-error';

/**
 * @return {boolean} Whether the form would pass Place order's check.
 */
export function formIsValid() {
	return ! select( VALIDATION_STORE ).hasValidationErrors();
}

/**
 * @return {boolean} Whether the form would pass Place order's check, as it changes.
 */
export function useFormIsValid() {
	return useSelect(
		( selectFrom ) =>
			! selectFrom( VALIDATION_STORE ).hasValidationErrors(),
		[]
	);
}

/**
 * What Place order does with an invalid form: every error shown, the first one in view.
 */
export function showFormErrors() {
	dispatch( VALIDATION_STORE ).showAllValidationErrors();
	// The errors render on the next tick.
	window.setTimeout( () => {
		const field = document.querySelector( FIRST_ERROR );
		if ( ! field ) {
			return;
		}
		field.scrollIntoView( { block: 'center' } );
		field.querySelector( 'input, select, textarea' )?.focus();
	}, 50 );
}

/**
 * What Place order would send to the store besides the payment method.
 *
 * @return {Object} customer_note, additional_fields, billing_address and shipping_address.
 */
export function formData() {
	const checkout = select( CHECKOUT_STORE );
	const customer = select( CART_STORE ).getCustomerData() ?? {};

	return {
		customer_note: checkout.getOrderNotes() ?? '',
		additional_fields: checkout.getAdditionalFields() ?? {},
		billing_address: customer.billingAddress ?? {},
		shipping_address: customer.shippingAddress ?? {},
	};
}
