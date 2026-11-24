/**
 * Which state the Express Component shows:
 *   hidden   empty cart, a subscription, or a final total of zero
 *   ready    the store can price a session
 *
 * A convenience: the store routes apply every rule again. The shipping form never decides: the wallet
 * supplies the address and the store prices the shipping options for it.
 *
 * @param {Object}  input
 * @param {number}  input.itemCount       Lines in the cart.
 * @param {boolean} input.hasSubscription A subscription product is in the cart.
 * @param {boolean} input.nothingToPay    The cart's total without shipping is zero.
 * @return {{status: string}} The state.
 */
export function expressReadiness( {
	itemCount,
	hasSubscription,
	nothingToPay = false,
} ) {
	if ( ! itemCount || hasSubscription || nothingToPay ) {
		return { status: 'hidden' };
	}

	return { status: 'ready' };
}
