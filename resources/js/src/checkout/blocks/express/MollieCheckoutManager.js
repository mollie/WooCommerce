/**
 * The page's side of mollie.js v2: Mollie2.Checkout, the express component, and the one submit handler.
 *
 * Always window.Mollie2 — the name the ?compatible bundle publishes itself under — never
 * window.Mollie, which belongs to v1, and no fallback between the two. The client access token is
 * passed straight to Mollie2.Checkout and not kept: dropping the checkout on unmount drops it too.
 *
 * Mounting is asynchronous and the component may be remounted while a mount is still running (a new
 * price). A generation number makes sure only the latest mount stays on the page.
 */
export class MollieCheckoutManager {
	/**
	 * @return {boolean} Whether mollie.js v2 is loaded under its compatible name.
	 */
	static isAvailable() {
		return typeof window.Mollie2?.Checkout === 'function';
	}

	/**
	 * @param {{locale: string, buttons: Object}} options  Passed to Mollie2.Checkout and create().
	 * @param {Function}                          onSubmit Called with Mollie's submit event.
	 */
	constructor( { locale, buttons }, onSubmit ) {
		this.locale = locale;
		this.buttons = buttons;
		this.onSubmit = onSubmit;
		this.checkout = null;
		this.component = null;
		this.generation = 0;
		this.handleSubmit = ( event ) => this.onSubmit( event );
	}

	/**
	 * Replaces whatever is mounted with a component for this session.
	 *
	 * @param {string}      clientAccessToken From POST express/session.
	 * @param {HTMLElement} target            Where the component is mounted.
	 * @return {Promise<boolean>} False when a newer mount or an unmount overtook this one.
	 */
	async mount( clientAccessToken, target ) {
		const generation = ++this.generation;
		await this.release();
		if ( generation !== this.generation ) {
			return false;
		}

		const checkout = window.Mollie2.Checkout( clientAccessToken, {
			locale: this.locale,
		} );
		checkout.on( 'submit', this.handleSubmit );
		const component = checkout.create( 'express-component', {
			buttons: this.buttons,
		} );
		// Kept before mounting, so an unmount that arrives meanwhile removes this one too.
		this.checkout = checkout;
		this.component = component;
		await component.mount( target );

		return generation === this.generation;
	}

	/**
	 * Removes the component and forgets the checkout (and with it the token).
	 */
	async unmount() {
		this.generation++;
		await this.release();
	}

	async release() {
		const { checkout, component } = this;
		this.checkout = null;
		this.component = null;
		if ( checkout && typeof checkout.off === 'function' ) {
			checkout.off( 'submit', this.handleSubmit );
		}
		if ( component ) {
			try {
				await component.unmount();
			} catch ( e ) {
				// Already gone: nothing to remove.
			}
		}
	}
}
