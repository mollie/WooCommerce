/**
 * Main store registration for Mollie WooCommerce blocks
 */
import { createReduxStore, register  } from '@wordpress/data';
import reducer from './reducer';
import * as actions from './actions';
import selectors from './selectors';

export const MOLLIE_STORE_KEY = 'mollie-payments';

export const mollieStore = createReduxStore( MOLLIE_STORE_KEY, {
	reducer,
	actions,
	selectors,
} );

register( mollieStore );
