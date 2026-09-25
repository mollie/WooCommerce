/**
 * Which of its three states the Express Component shows, from what the block checkout already knows.
 *
 *   hidden   the cart cannot be paid this way: it is empty or holds a subscription
 *   blocked  the cart ships and the checkout form cannot price it yet: a required shipping field is
 *            empty, no rate is selected, the shipping cost is not known, or the totals are still
 *            being calculated
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
 * @param {boolean}  input.hasShippingAmount      The store has priced the shipping for this address.
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
	hasShippingAmount,
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
	// A country on its own is enough for WooCommerce to offer a rate, which is how a session came to
	// be priced for an address the shopper had not finished. Express waits for
	// the whole address AND for a shipping cost that belongs to it, because the session's amount is
	// fixed the moment it is created.
	if ( missingField || ! hasSelectedRate || ! hasShippingAmount || isCalculating ) {
		return { status: 'blocked', reason: 'shipping_incomplete' };
	}

	return { status: 'ready' };
}
