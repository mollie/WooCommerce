import { select, subscribe } from '@wordpress/data';
import { PAYMENT_STORE_KEY, MOLLIE_STORE_KEY } from '../store';
import { mollieComponentsManager } from './MollieComponentsManager';

export function initializeMollieComponentsWithStoreSubscription( config ) {
    let isInitialized = false;
    let unsubscribeInit = null;
    let unsubscribeMounting = null;

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

    const initializeComponents = async () => {
        if ( isInitialized ) {
            return;
        }

        // prevent multiple concurrent initializations
        isInitialized = true;

        try {
            console.log( 'Mollie: Initializing Components Manager' );
            if ( ! config.merchantProfileId ) {
                console.error( 'Mollie merchant profile ID not found' );
                isInitialized = false; // Reset on error
                return;
            }

            await mollieComponentsManager.initialize( {
                merchantProfileId: config.merchantProfileId,
                options: config.options || {},
            } );

            console.log( 'Mollie: Components Manager initialized successfully' );

            if ( unsubscribeInit ) {
                unsubscribeInit();
                unsubscribeInit = null;
            }

            setupComponentMountingSubscription();
        } catch ( error ) {
            console.error( 'Mollie: Failed to initialize Components Manager:', error );
            isInitialized = false;
        }
    };

    /**
     * Subscribe to BOTH payment method AND container changes
     */
    const setupComponentMountingSubscription = () => {
        let previousPaymentMethod = null;
        let previousContainer = null;
        let componentsAreMounted = false;

        unsubscribeMounting = subscribe( () => {
            try {
                const mollieStore = select( MOLLIE_STORE_KEY );
                const activePaymentMethod = mollieStore.getActivePaymentMethod();
                const container = mollieStore.getComponentContainer( activePaymentMethod );

                const paymentMethodChanged = previousPaymentMethod !== activePaymentMethod;
                const containerChanged = previousContainer !== container;

                const isCreditCard = activePaymentMethod === 'mollie_wc_gateway_creditcard';
                const wasCreditCard = previousPaymentMethod === 'mollie_wc_gateway_creditcard';

                // UNMOUNT: switched away from credit card
                if ( wasCreditCard && ! isCreditCard && componentsAreMounted ) {
                    console.log( 'Mollie: Unmounting components, switched away from credit card' );
                    mollieComponentsManager.unmountComponents( previousPaymentMethod );
                    componentsAreMounted = false;
                }

                // MOUNT: switched to credit card and have container
                const shouldMount = isCreditCard
                    && container
                    && (paymentMethodChanged || containerChanged)
                    && ! componentsAreMounted;

                // Update tracking
                previousPaymentMethod = activePaymentMethod;
                previousContainer = container;

                if ( ! shouldMount ) {
                    return;
                }

                console.log( 'Mollie: Mounting components for', activePaymentMethod, {
                    paymentMethodChanged,
                    containerChanged,
                    hasContainer: !! container
                } );

                const mountComponents = async () => {
                    try {
                        await mollieComponentsManager.mountComponents(
                            activePaymentMethod,
                            config.componentsAttributes,
                            config.componentsSettings,
                            container
                        );
                        componentsAreMounted = true;
                        console.log( 'Mollie: Components mounted successfully for', activePaymentMethod );
                    } catch ( error ) {
                        console.error( 'Mollie: Failed to mount components:', error );
                        componentsAreMounted = false;
                    }
                };

                mountComponents();
            } catch ( error ) {
                console.warn( 'Mollie: Error in component mounting subscription:', error );
            }
        }, MOLLIE_STORE_KEY );
    };

    const checkoutStateChangeHandler = () => {
        if ( isInitialized ) {
            return;
        }

        if ( isCheckoutReady() ) {
            console.log( 'Mollie: Checkout ready, initializing components' );
            initializeComponents();
        }
    };

    if ( isCheckoutReady() ) {
        initializeComponents();
    } else {
        console.log( 'Mollie: Subscribing to checkout store for initialization' );
        unsubscribeInit = subscribe( checkoutStateChangeHandler, 'wc/store/checkout' );
    }

    return () => {
        if ( unsubscribeInit ) unsubscribeInit();
        if ( unsubscribeMounting ) unsubscribeMounting();
        mollieComponentsManager.cleanup();
    };
}
