/* global wc */
import './store/index.js';
import { select } from '@wordpress/data';
import { setUpMollieBlockCheckoutListeners } from './store/storeListeners';
import { MOLLIE_STORE_KEY, PAYMENT_STORE_KEY } from './store';
import {
	registerAllContentHooks,
	registerExpressPaymentMethodHooks,
	registerGatewayRegistrationHooks
} from './registration/libraryHooksRegistrar';
import { buildRegistrationContext } from './registration/contextBuilder';
import {initializeMollieComponentsWithStoreSubscription} from "./services/MollieComponentsInitializer";

/**
 * Initialization with mollieComponentsManager
 * Hooks for content and shouldRegister
 * The main registration is done in the paymentGateway lib
 *
 */
( function ( { mollieBlockData, wc, _ } ) {
	if ( _.isEmpty( mollieBlockData ) ) {
		console.warn( 'Mollie: No block data available' );
		return;
	}

	const paymentStore = select( PAYMENT_STORE_KEY );
	if ( ! paymentStore ) {
		console.warn( 'Mollie: Payment store not available' );
		return;
	}

	try {
		const { gatewayData } = mollieBlockData.gatewayData;
		const context = buildRegistrationContext( wc );

		registerAllContentHooks( gatewayData, context );
		registerGatewayRegistrationHooks( gatewayData );
		registerExpressPaymentMethodHooks( gatewayData );
		setUpMollieBlockCheckoutListeners( MOLLIE_STORE_KEY );

		if ( mollieBlockData.gatewayData.componentData ) {
			initializeMollieComponentsWithStoreSubscription(
				mollieBlockData.gatewayData.componentData
			);
		}

	} catch ( error ) {
		console.error( 'Mollie: Initialization failed:', error );
	}
} )( window, wc );
