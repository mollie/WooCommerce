/**
 * Which of its three states the Express Component shows, from what the block checkout already knows.
 *
 *   hidden   the cart cannot be paid this way: it is empty or holds a subscription
 *   blocked  the cart ships and the checkout form cannot price it yet: a required shipping field is
 *            empty, no rate is selected, or the totals are still being calculated
 *   ready    the store can price a session
 *
 * A convenience, not a control: the store routes apply every one of these rules again. Every wallet
 * waits for the form, so the answer does not depend on the wallet.
 *
 * @param {Object}   input
 * @param {number}   input.itemCount              Lines in the cart.
 * @param {boolean}  input.hasSubscription        A subscription product is in the cart.
 * @param {boolean}  input.needsShipping          Something in the cart is shipped.
 * @param {Object}   input.shippingAddress        The shipping fields as the form holds them.
 * @param {string[]} input.requiredShippingFields The fields required for the address's country.
 * @param {boolean}  input.hasSelectedRate        Every package has a selected shipping rate.
 * @param {boolean}  input.isCalculating          The store is recalculating the totals.
 * @return {{status: string, reason?: string}} The state.
 */
export function expressReadiness( {
	itemCount,
	hasSubscription,
	needsShipping,
	shippingAddress = {},
	requiredShippingFields = [],
	hasSelectedRate,
	isCalculating,
} ) {
	if ( ! itemCount || hasSubscription ) {
		return { status: 'hidden' };
	}
	if ( ! needsShipping ) {
		return { status: 'ready' };
	}

	const missingField = requiredShippingFields.some(
		( field ) => String( shippingAddress?.[ field ] ?? '' ).trim() === ''
	);
	if ( missingField || ! hasSelectedRate || isCalculating ) {
		return { status: 'blocked', reason: 'shipping_incomplete' };
	}

	return { status: 'ready' };
}
