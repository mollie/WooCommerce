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
                // Add custom classes to payment method icons
                addCustomClassesToPaymentIcons();
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

/**
 * Adds custom classes to payment method icons
 */
function addCustomClassesToPaymentIcons() {
    const addClassesToIcons = () => {
        // Find all containers with payment method icons
        const iconContainers = document.querySelectorAll('.wc-block-components-payment-method-icons');

        iconContainers.forEach(container => {
            const images = container.querySelectorAll('img');

            images.forEach(img => {
                if (!img.classList.contains('mollie-gateway-icon')) {
                    img.classList.add('mollie-gateway-icon');
                }
            });
        });
    };

    addClassesToIcons();
    // Set up a MutationObserver to handle dynamically added icons
    const observer = new MutationObserver((mutations) => {
        let shouldUpdate = false;

        mutations.forEach(mutation => {
            // Check if new nodes were added
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(node => {
                    // Check if the added node contains payment method icons
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList?.contains('wc-block-components-payment-method-icons') ||
                            node.querySelector?.('.wc-block-components-payment-method-icons')) {
                            shouldUpdate = true;
                        }
                    }
                });
            }
        });

        if (shouldUpdate) {
            // Small delay to ensure DOM is fully updated
            setTimeout(addClassesToIcons, 10);
        }
    });

    // Start observing the document for changes
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Clean up observer when page unloads (optional)
    window.addEventListener('beforeunload', () => {
        observer.disconnect();
    });
}
