import './src/store/index.js';
import { setUpMollieBlockCheckoutListeners } from "./src/store/storeListeners";
import { MOLLIE_STORE_KEY } from "./src/store";
import { registerAllPaymentMethods } from './src/registration/paymentRegistrar';
import { buildRegistrationContext } from './src/initialization/contextBuilder';
import { mollieComponentsManager } from './src/services/MollieComponentsManager';

/**
 * Main Mollie WooCommerce Blocks initialization with TokenManager
 */
(function ({mollieBlockData, wc, _, jQuery}) {
    if (_.isEmpty(mollieBlockData)) {
        console.warn('Mollie: No block data available');
        return;
    }

    try {
        const { gatewayData } = mollieBlockData.gatewayData;
        const context = buildRegistrationContext(wc, jQuery);

        registerAllPaymentMethods(gatewayData, context);
        setUpMollieBlockCheckoutListeners(MOLLIE_STORE_KEY);

        if (window.mollieComponentsSettings) {
            const initTokenManager = async () => {
                try {
                    await mollieComponentsManager.initialize({
                        merchantProfileId: window.mollieComponentsSettings.merchantProfileId,
                        options: window.mollieComponentsSettings.options || {}
                    });
                    console.log('Mollie TokenManager initialized');
                } catch (error) {
                    console.error('Failed to initialize TokenManager:', error);
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTokenManager);
            } else {
                initTokenManager();
            }
        }

        window.addEventListener('beforeunload', () => {
            mollieComponentsManager.cleanup();
        });

    } catch (error) {
        console.error('Mollie: Initialization failed:', error);
    }
})(window, wc);
