/**
 * Main store registration for Mollie WooCommerce blocks
 */
import { createReduxStore, register } from '@wordpress/data';
import reducer from './reducer';
import * as actions from './actions';
import selectors from './selectors';
import { MOLLIE_STORE_KEY } from './constants';

export { MOLLIE_STORE_KEY } from './constants';
export const PAYMENT_STORE_KEY = 'wc/store/payment';

export const mollieStore = createReduxStore( MOLLIE_STORE_KEY, {
	reducer,
	actions,
	selectors,
} );

register( mollieStore );
