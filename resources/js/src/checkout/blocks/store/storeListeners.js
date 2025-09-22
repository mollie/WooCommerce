import { MOLLIE_STORE_KEY, PAYMENT_STORE_KEY } from './index';
import { select, dispatch, subscribe } from '@wordpress/data';

export const setUpMollieBlockCheckoutListeners = () => {
	let currentPaymentMethod;
	const checkoutStoreCallback = () => {
		try {
			const paymentStore = select( PAYMENT_STORE_KEY );

			const paymentMethod = paymentStore.getActivePaymentMethod();
			if ( currentPaymentMethod !== paymentMethod ) {
				dispatch( MOLLIE_STORE_KEY )
					.setActivePaymentMethod( paymentMethod );
				currentPaymentMethod = paymentMethod;
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.log( 'Checkout store not ready yet:', error );
		}
	};

	const unsubscribeCheckoutStore = subscribe(
		checkoutStoreCallback,
		PAYMENT_STORE_KEY
	);
	checkoutStoreCallback();

	return { unsubscribeCheckoutStore };
};
