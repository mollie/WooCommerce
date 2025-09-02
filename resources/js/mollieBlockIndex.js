import './src/store/index.js';
import { setUpMollieBlockCheckoutListeners } from "./src/store/storeListeners";
import { MOLLIE_STORE_KEY } from "./src/store";
import { registerAllPaymentMethods } from './src/registration/paymentRegistrar';
import { buildRegistrationContext } from './src/initialization/contextBuilder';

/**
 * Main Mollie WooCommerce Blocks initialization
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

    } catch (error) {
        console.error('Mollie: Initialization failed:', error);
    }
})(window, wc);
