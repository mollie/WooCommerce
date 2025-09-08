
import molliePaymentMethod from '../../blocks/molliePaymentMethod';
import { PAYMENT_TYPES } from '../config/paymentConstants';
import { getPaymentConfig, isEditorContext } from '../utils/paymentUtils';
import { ApplePayUtils } from '../utils/applePayUtils';

/**
 * Payment method registration strategies
 * Contains different strategies for registering payment methods based on their type
 * @namespace paymentStrategies
 */
export const paymentStrategies = {
    /**
     * Strategy for registering regular payment methods
     * Uses the standard WooCommerce payment method registration
     * @param {PaymentItem} item - The payment method item to register
     * @param {PaymentContext} context - The registration context containing WC blocks registry and other dependencies
     * @returns {Object} The registered payment method object
     * @example
     * const item = {
     *     name: 'mollie_wc_gateway_ideal',
     *     title: 'iDEAL',
     *     description: 'Pay with iDEAL'
     * };
     * const context = {
     *     wc: { wcBlocksRegistry: { registerPaymentMethod } },
     *     jQuery: $,
     *     requiredFields: ['email'],
     *     isPhoneFieldVisible: true
     * };
     * paymentStrategies.regular(item, context);
     */
    [PAYMENT_TYPES.REGULAR]: (item, context) => {
        const { registerPaymentMethod } = context.wc.wcBlocksRegistry;
        return registerPaymentMethod(
            molliePaymentMethod(item, context.jQuery, context.requiredFields, context.isPhoneFieldVisible)
        );
    },

    /**
     * Strategy for registering express payment methods
     * Handles special configuration for express payments like Apple Pay
     * @param {PaymentItem} item - The payment method item to register as express
     * @param {PaymentContext} context - The registration context containing WC blocks registry and other dependencies
     * @returns {Object} The registered express payment method object
     * @example
     * const applePayItem = {
     *     name: 'mollie_wc_gateway_applepay',
     *     title: 'Apple Pay',
     *     description: 'Pay with Apple Pay',
     *     isExpressEnabled: true
     * };
     * const context = {
     *     wc: { wcBlocksRegistry: { registerExpressPaymentMethod } },
     *     jQuery: $,
     *     requiredFields: ['email'],
     *     isPhoneFieldVisible: true
     * };
     * paymentStrategies.express(applePayItem, context);
     */
    [PAYMENT_TYPES.EXPRESS]: (item, context) => {
        const { registerExpressPaymentMethod } = context.wc.wcBlocksRegistry;
        const config = getPaymentConfig(item);

        // Get components based on payment method type
        const components = ApplePayUtils.isApplePayMethod(item)
            ? ApplePayUtils.getApplePayComponents()
            : { content: null, edit: null };

        return registerExpressPaymentMethod({
            name: `${item.name}_express`,
            title: `${item.title} Express`,
            description: item.description,
            content: components.content,
            edit: components.edit,
            ariaLabel: item.title,
            canMakePayment: () => isEditorContext() ||
                (config.requiresAppleSession ? ApplePayUtils.isAppleSessionAvailable() : true),
            paymentMethodId: item.name,
            gatewayId: item.name,
            supports: {
                features: ['products'],
                style: ['height', 'borderRadius'],
                ...config.supports
            },
        });
    }
};
