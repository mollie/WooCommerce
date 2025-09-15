import { DEFAULT_CONFIG } from '../constants/paymentConstants';
import { ApplePayUtils } from './applePayUtils';

/**
 * General payment utility functions
 * @namespace PaymentUtils
 */

/**
 * Checks if the current context is the WordPress block editor
 * @return {boolean} True if in block editor context, false otherwise
 * @example
 * if (isEditorContext()) {
 *     // Load editor-specific functionality
 * }
 */
export const isEditorContext = () => wp?.blocks?.isEditorContext();

/**
 * Generates a payment configuration object by merging default config with item-specific config
 * Includes special handling for Apple Pay backward compatibility
 * @param {Object}  item                    - The payment method item
 * @param {string}  item.name               - The payment method name
 * @param {Object}  [item.config]           - Custom configuration for the payment method
 * @param {boolean} [item.isExpressEnabled] - Whether express payment is enabled (Apple Pay specific)
 * @return {Object} Merged configuration object with default and custom settings
 * @example
 * const paymentItem = {
 *     name: 'mollie_wc_gateway_applepay',
 *     config: { customSetting: true },
 *     isExpressEnabled: true
 * };
 * const config = getPaymentConfig(paymentItem);
 * // Returns merged config with Apple Pay specific settings
 */
export const getPaymentConfig = ( item ) => ( {
	...DEFAULT_CONFIG,
	...item.config,
	// Backward compatibility for Apple Pay
	...( ApplePayUtils.isApplePayMethod( item ) && {
		express: item.isExpressEnabled || false,
		requiresAppleSession: true,
	} ),
} );

/**
 * Validates a payment method item to ensure it has required properties
 * @param {Object} item        - The payment method item to validate
 * @param {string} [item.name] - The payment method name (required)
 * @return {boolean} True if validation passes
 * @throws {Error} Throws error if payment method is missing required name property
 * @example
 * try {
 *     validatePaymentItem({ name: 'mollie_wc_gateway_ideal' });
 *     // Validation passed
 * } catch (error) {
 *     console.error('Invalid payment method:', error.message);
 * }
 */
export const validatePaymentItem = ( item ) => {
	if ( ! item?.name ) {
		throw new Error( `Invalid payment method: missing name` );
	}
	return true;
};

/**
 * Determines the visibility of the phone field based on DOM data attributes
 * Checks for a data attribute that controls phone field display
 * @return {boolean} True if phone field should be hidden, false if it should be shown
 */
export const shouldHidePhoneField = () => {
	const shippingPhone = document.getElementById('shipping-phone');
	const billingPhone = document.getElementById('billing-phone');
	const phone = shippingPhone || billingPhone;
	// hide phone field if it is required
	return phone? (phone.hasAttribute('required') || phone.getAttribute('aria-required') === 'true') : false;
};
