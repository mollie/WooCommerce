import {MOLLIE_STORE_KEY, PAYMENT_STORE_KEY} from './index';
import {dispatch, select, subscribe} from '@wordpress/data';
import {initializeMollieComponentsWithStoreSubscription} from '../services/MollieComponentsInitializer';

export const setUpMollieBlockCheckoutListeners = () => {
	let currentPaymentMethod;
	const checkoutStoreCallback = () => {
		try {
			const paymentStore = select( PAYMENT_STORE_KEY );

			const paymentMethod = paymentStore.getActivePaymentMethod();
			if ( currentPaymentMethod !== paymentMethod ) {
				dispatch( MOLLIE_STORE_KEY ).setActivePaymentMethod(
					paymentMethod
				);
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

export const initializeMollieStoreListeners = () => {
    let storeInitialized = false;
    let unsubscribe = null;

    const initializeStoreDependent = () => {
        if (storeInitialized) {
            return;
        }

        const paymentStore = select(PAYMENT_STORE_KEY);
        if (!paymentStore) {
            return;
        }

        storeInitialized = true;

        if (unsubscribe) {
            unsubscribe();
            unsubscribe = null;
        }

        setUpMollieBlockCheckoutListeners();

        const componentConfig = select(MOLLIE_STORE_KEY).getComponentConfig();
        if (componentConfig) {
            initializeMollieComponentsWithStoreSubscription(componentConfig);
        }

        addCustomClassesToPaymentIcons();

        window.addEventListener('pageshow', (event) => {
            if (event.persisted || performance.getEntriesByType('navigation')[0]?.type === 'back_forward') {
                try {
                    const currentStatus = wp.data.select('wc/store/checkout').getCheckoutStatus();
                    if (currentStatus === 'complete') {
                        window.location.reload();
                    }
                } catch (error) {
                    console.warn('Mollie: Could not reset checkout state on back navigation:', error);
                }
            }
        });
    };

    initializeStoreDependent();

    if (!storeInitialized) {
        unsubscribe = subscribe(initializeStoreDependent, PAYMENT_STORE_KEY);
    }
};

function addCustomClassesToPaymentIcons() {

    const addClassesToIcons = () => {
        const iconContainers = document.querySelectorAll('[id*="mollie_wc_gateway"] .wc-block-components-payment-method-icons');

        iconContainers.forEach((container) => {
            const images = container.querySelectorAll('img');

            images.forEach((img) => {
                if (!img.classList.contains('mollie-gateway-icon')) {
                    img.classList.add('mollie-gateway-icon');
                }
            });
        });
    };

    addClassesToIcons();

    const observer = new MutationObserver((mutations) => {
        let shouldUpdate = false;

        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach((node) => {
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
            setTimeout(addClassesToIcons, 10);
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    window.addEventListener('beforeunload', () => {
        observer.disconnect();
    });
}
