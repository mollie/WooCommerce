import molliePaymentMethod from '../components/molliePaymentMethod';
import { PAYMENT_TYPES } from '../../../shared/constants/paymentConstants';
import {
	getPaymentConfig,
	isEditorContext,
} from '../../../shared/utils/paymentUtils';
import { ApplePayUtils } from '../../../shared/utils/applePayUtils';

/**
 * @typedef {Object} PaymentItem
 * @property {string}  name               - The payment method name/identifier
 * @property {string}  title              - The display title of the payment method
 * @property {string}  description        - The description of the payment method
 * @property {Object}  [config]           - Custom configuration for the payment method
 * @property {boolean} [isExpressEnabled] - Whether express payment is enabled
 */

/**
 * @typedef {Object} PaymentContext
 * @property {Object}        wc                                               - WooCommerce blocks object
 * @property {Object}        wc.wcBlocksRegistry                              - WooCommerce blocks registry
 * @property {Function}      wc.wcBlocksRegistry.registerPaymentMethod        - Function to register regular payment methods
 * @property {Function}      wc.wcBlocksRegistry.registerExpressPaymentMethod - Function to register express payment methods
 * @property {Object}        jQuery                                           - jQuery object
 * @property {Array<string>} requiredFields                                   - Array of required field names
 * @property {boolean}       isPhoneFieldVisible                              - Whether the phone field should be visible
 */

/**
 * @typedef {Object} PaymentMethodSupports
 * @property {Array<string>} features - Supported features (e.g., ['products'])
 * @property {Array<string>} style    - Supported style properties (e.g., ['height', 'borderRadius'])
 */

/**
 * @typedef {Object} ExpressPaymentMethodConfig
 * @property {string}                name            - The express payment method name
 * @property {string}                title           - The display title
 * @property {string}                description     - The payment method description
 * @property {React.Element|null}    content         - The content component for frontend
 * @property {React.Element|null}    edit            - The edit component for block editor
 * @property {string}                ariaLabel       - Accessibility label
 * @property {Function}              canMakePayment  - Function to determine if payment can be made
 * @property {string}                paymentMethodId - The payment method identifier
 * @property {string}                gatewayId       - The gateway identifier
 * @property {PaymentMethodSupports} supports        - Supported features and styles
 */

/**
 * Payment method registration strategies
 * Contains different strategies for registering payment methods based on their type
 * @namespace paymentStrategies
 */
export const paymentStrategies = {
	/**
	 * Strategy for registering regular payment methods
	 * Uses the standard WooCommerce payment method registration
	 * @param {PaymentItem}    item    - The payment method item to register
	 * @param {PaymentContext} context - The registration context containing WC blocks registry and other dependencies
	 * @return {Object} The registered payment method object
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
	[ PAYMENT_TYPES.REGULAR ]: ( item, context ) => {
		const { registerPaymentMethod } = context.wc.wcBlocksRegistry;
		return registerPaymentMethod(
			molliePaymentMethod(
				item,
				context.jQuery,
				context.requiredFields,
				context.isPhoneFieldVisible
			)
		);
	},

	/**
	 * Strategy for registering express payment methods
	 * Handles special configuration for express payments like Apple Pay
	 * @param {PaymentItem}    item    - The payment method item to register as express
	 * @param {PaymentContext} context - The registration context containing WC blocks registry and other dependencies
	 * @return {Object} The registered express payment method object
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
	[ PAYMENT_TYPES.EXPRESS ]: ( item, context ) => {
		const { registerExpressPaymentMethod } = context.wc.wcBlocksRegistry;
		const config = getPaymentConfig( item );

		// Get components based on payment method type
		const components = ApplePayUtils.isApplePayMethod( item )
			? ApplePayUtils.getApplePayComponents()
			: { content: null, edit: null };

		return registerExpressPaymentMethod( {
			name: `${ item.name }_express`,
			title: `${ item.title } Express`,
			description: item.description,
			content: components.content,
			edit: components.edit,
			ariaLabel: item.title,
			canMakePayment: () =>
				isEditorContext() ||
				( config.requiresAppleSession
					? ApplePayUtils.isAppleSessionAvailable()
					: true ),
			paymentMethodId: item.name,
			gatewayId: item.name,
			supports: {
				features: [ 'products' ],
				style: [ 'height', 'borderRadius' ],
				...config.supports,
			},
		} );
	},
};
