
import { APPLE_PAY_GATEWAY_NAME } from '../config/paymentConstants';

/**
 * Apple Pay utility functions - centralized Apple Pay logic
 * @namespace ApplePayUtils
 */
export const ApplePayUtils = {
    /**
     * The gateway name constant for Apple Pay
     * @type {string}
     * @readonly
     */
    GATEWAY_NAME: APPLE_PAY_GATEWAY_NAME,

    /**
     * Checks if a payment method item is an Apple Pay method
     * @param {Object} item - The payment method item to check
     * @param {string} item.name - The name of the payment method
     * @returns {boolean} True if the item is an Apple Pay method, false otherwise
     * @example
     * const paymentMethod = { name: 'mollie_wc_gateway_applepay' };
     * const isApplePay = ApplePayUtils.isApplePayMethod(paymentMethod); // true
     */
    isApplePayMethod: (item) => item.name === ApplePayUtils.GATEWAY_NAME,

    /**
     * Checks if Apple Pay session is available in the current browser environment
     * @returns {boolean} True if ApplePaySession is available and can make payments, false otherwise
     * @example
     * if (ApplePayUtils.isAppleSessionAvailable()) {
     *     // Initialize Apple Pay functionality
     * }
     */
    isAppleSessionAvailable: () =>
        typeof window.ApplePaySession === "function" && window.ApplePaySession.canMakePayments(),

    /**
     * Determines if Apple Pay can be registered in the current context
     * Checks if we're in the WordPress block editor or if Apple Pay session is available
     * @returns {boolean} True if Apple Pay can be registered, false otherwise
     * @example
     * if (ApplePayUtils.canRegisterApplePay()) {
     *     // Register Apple Pay payment method
     * }
     */
    canRegisterApplePay: () =>
        wp.blocks?.isEditorContext() || ApplePayUtils.isAppleSessionAvailable(),

    /**
     * Gets the Apple Pay React components for content and editor contexts
     * @returns {Object} Object containing content and edit components
     * @returns {React.Element} returns.content - The Apple Pay button component for frontend
     * @returns {React.Element} returns.edit - The Apple Pay button component for block editor
     * @example
     * const { content, edit } = ApplePayUtils.getApplePayComponents();
     * // Use content component in frontend, edit component in block editor
     */
    getApplePayComponents: () => ({
        content: wp.element.createElement(
            () => import('../../blocks/ApplePayButtonComponent').then(m => m.default)
        ),
        edit: wp.element.createElement(
            () => import('../../blocks/ApplePayButtonEditorComponent').then(m => m.default)
        )
    })
};
