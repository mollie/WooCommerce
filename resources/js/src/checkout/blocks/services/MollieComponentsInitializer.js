// services/MollieComponentsInitializer.js

import { select, subscribe } from '@wordpress/data';
import { PAYMENT_STORE_KEY } from '../store';
import { mollieComponentsManager } from './MollieComponentsManager';

/**
 * Initialize Mollie Components by subscribing to WooCommerce checkout store
 * This avoids DOM polling and uses store-based lifecycle detection
 *
 * @param {Object} config - Mollie component configuration
 */
export function initializeMollieComponentsWithStoreSubscription( config ) {
    let isInitialized = false;
    let unsubscribeInit = null;
    let unsubscribeCleanup = null;
    let previousCheckoutStatus = null;

    /**
     * Check if checkout is ready by examining store state
     */
    const isCheckoutReady = () => {
        try {
            const checkoutStore = select( 'wc/store/checkout' );
            const paymentStore = select( PAYMENT_STORE_KEY );

            if ( ! checkoutStore || ! paymentStore ) {
                return false;
            }

            const isCalculating = checkoutStore.isCalculating();
            const hasError = checkoutStore.hasError();

            return ! isCalculating && ! hasError;
        } catch ( error ) {
            console.warn( 'Mollie: Error checking checkout readiness:', error );
            return false;
        }
    };

    /**
     * Initialize Mollie Components SDK
     */
    const initializeComponents = async () => {
        if ( isInitialized ) {
            return;
        }

        try {
            console.log( 'Mollie: Initializing Components Manager' );
            if ( ! config.merchantProfileId ) {
                console.error( 'Mollie merchant profile ID not found' );
                return;
            }
            await mollieComponentsManager.initialize( {
                merchantProfileId: config.merchantProfileId,
                options: config.options || {},
            } );

            isInitialized = true;
            console.log( 'Mollie: Components Manager initialized successfully' );

            // Unsubscribe from init after successful initialization
            if ( unsubscribeInit ) {
                unsubscribeInit();
                unsubscribeInit = null;
            }

            // Start cleanup subscription after initialization
            setupCleanupSubscription();
        } catch ( error ) {
            console.error( 'Mollie: Failed to initialize Components Manager:', error );
            isInitialized = false;
        }
    };

    /**
     * Setup cleanup subscription to monitor checkout state changes
     */
    const setupCleanupSubscription = () => {
        if ( unsubscribeCleanup ) {
            return; // Already subscribed
        }

        unsubscribeCleanup = subscribe(() => {
            try {
                const checkoutStore = select('wc/store/checkout');
                if (!checkoutStore) return;

                const currentStatus = checkoutStore.getCheckoutStatus();

                // Cleanup when checkout is being destroyed or reset
                if (previousCheckoutStatus &&
                    (currentStatus === 'idle' || currentStatus === 'before_processing') &&
                    previousCheckoutStatus !== currentStatus) {
                    console.log('Mollie: Checkout state changed, cleaning up components');
                    mollieComponentsManager.cleanup();
                    isInitialized = false;
                }

                previousCheckoutStatus = currentStatus;
            } catch (error) {
                console.warn('Mollie: Error in cleanup subscription:', error);
            }
        }, 'wc/store/checkout');
    };

    /**
     * Store subscription callback for initialization
     */
    const checkoutStateChangeHandler = () => {
        if ( isInitialized ) {
            return;
        }

        if ( isCheckoutReady() ) {
            console.log( 'Mollie: Checkout ready, initializing components' );
            initializeComponents();
        }
    };

    // Immediate check in case checkout is already ready
    if ( isCheckoutReady() ) {
        initializeComponents();
    } else {
        // Subscribe to checkout store changes for initialization
        console.log( 'Mollie: Subscribing to checkout store for initialization' );
        unsubscribeInit = subscribe(
            checkoutStateChangeHandler,
            'wc/store/checkout'
        );
    }

    // Return cleanup function for manual cleanup if needed
    return () => {
        if (unsubscribeInit) unsubscribeInit();
        if (unsubscribeCleanup) unsubscribeCleanup();
        mollieComponentsManager.cleanup();
    };
}
