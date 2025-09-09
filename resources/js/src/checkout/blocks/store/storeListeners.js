import { MOLLIE_STORE_KEY } from './index';

export const setUpMollieBlockCheckoutListeners = () => {
	let currentPaymentMethod;
	const PAYMENT_STORE_KEY = 'wc/store/payment';
	const checkoutStoreCallback = () => {
		try {
			const paymentStore = wp.data.select( PAYMENT_STORE_KEY );

			const paymentMethod = paymentStore.getActivePaymentMethod();
			if ( currentPaymentMethod !== paymentMethod ) {
				wp.data
					.dispatch( MOLLIE_STORE_KEY )
					.setActivePaymentMethod( paymentMethod );
				currentPaymentMethod = paymentMethod;
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.log( 'Checkout store not ready yet:', error );
		}
	};

	const unsubscribeCheckoutStore = wp.data.subscribe(
		checkoutStoreCallback,
		PAYMENT_STORE_KEY
	);
	checkoutStoreCallback();

	return { unsubscribeCheckoutStore };
};
