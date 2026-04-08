import { PAYPAL_GATEWAY_NAME } from '../constants/paymentConstants';

export const PayPalUtils = {
    GATEWAY_NAME: PAYPAL_GATEWAY_NAME,

    isPayPalMethod: (item) => item.name === PayPalUtils.GATEWAY_NAME,

    canRegisterPayPal: () => true, // Always available, runtime checks in component
};
