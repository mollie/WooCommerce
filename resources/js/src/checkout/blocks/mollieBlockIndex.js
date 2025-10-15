/* global wc */
import './store/index.js';
import { select, subscribe } from '@wordpress/data';
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

    try {
        const { gatewayData } = mollieBlockData.gatewayData;
        const context = buildRegistrationContext( wc );

        // These don't depend on stores and should run before the library registration
        registerAllContentHooks( gatewayData, context );
        registerGatewayRegistrationHooks( gatewayData );
        registerExpressPaymentMethodHooks( gatewayData );

        // Wait for payment store
        let storeInitialized = false;
        let unsubscribe = null;

        const initializeStoreDependent = () => {
            if ( storeInitialized ) {
                return;
            }

            const paymentStore = select( PAYMENT_STORE_KEY );
            if ( ! paymentStore ) {
                return;
            }

            storeInitialized = true;

            // Unsubscribe from the store watcher
            if ( unsubscribe ) {
                unsubscribe();
                unsubscribe = null;
            }

            // Store-dependent features
            setUpMollieBlockCheckoutListeners( MOLLIE_STORE_KEY );

            if ( mollieBlockData.gatewayData.componentData ) {
                initializeMollieComponentsWithStoreSubscription(
                    mollieBlockData.gatewayData.componentData
                );
            }
        };

        initializeStoreDependent();

        // If not ready, subscribe to store changes
        if ( ! storeInitialized ) {
            unsubscribe = subscribe( initializeStoreDependent, PAYMENT_STORE_KEY );
        }

    } catch ( error ) {
        console.error( 'Mollie: Initialization failed:', error );
    }
} )( window, wc );
