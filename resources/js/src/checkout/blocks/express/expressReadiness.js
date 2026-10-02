/**
 * Which state the Express Component shows:
 *   hidden   empty cart, a subscription, or a final total of zero
 *   blocked  the cart ships and the form cannot price it yet
 *   ready    the store can price a session
 *
 * A convenience: the store routes apply every rule again.
 *
 * @param {Object}   input
 * @param {number}   input.itemCount              Lines in the cart.
 * @param {boolean}  input.hasSubscription        A subscription product is in the cart.
 * @param {boolean}  input.needsShipping          Something in the cart is shipped.
 * @param {Object}   input.shippingAddress        The shipping fields as the form holds them.
 * @param {string[]} input.requiredShippingFields The fields required for the address's country.
 * @param {boolean}  input.hasSelectedRate        Every package has a selected shipping rate.
 * @param {boolean}  input.hasShippingAmount      The store has priced the shipping for this address.
 * @param {boolean}  input.isCalculating          The store is recalculating the totals.
 * @param {boolean}  input.nothingToPay           The cart's total is zero.
 * @return {{status: string, reason?: string}} The state.
 */
export function expressReadiness( {
	itemCount,
	hasSubscription,
	needsShipping,
	shippingAddress = {},
	requiredShippingFields = [],
	hasSelectedRate,
	hasShippingAmount,
	isCalculating,
	nothingToPay = false,
} ) {
	if ( ! itemCount || hasSubscription ) {
		return { status: 'hidden' };
	}
	// Only a final total counts: a cart that ships may still get a shipping cost.
	const priced = nothingToPay ? { status: 'hidden' } : { status: 'ready' };
	if ( ! needsShipping ) {
		return priced;
	}

	const missingField = requiredShippingFields.some(
		( field ) => String( shippingAddress?.[ field ] ?? '' ).trim() === ''
	);
	// A country alone gets a rate from WooCommerce: wait for the whole address and its shipping cost.
	if ( missingField || ! hasSelectedRate || ! hasShippingAmount || isCalculating ) {
		return { status: 'blocked', reason: 'shipping_incomplete' };
	}

	return priced;
}
