import './store';
import { setUpMollieBlockCheckoutListeners } from "./store/storeListeners";
import { MOLLIE_STORE_KEY } from "./store";
import { registerAllPaymentMethods } from './registration/paymentRegistrar';
import { buildRegistrationContext } from './registration/contextBuilder';

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
