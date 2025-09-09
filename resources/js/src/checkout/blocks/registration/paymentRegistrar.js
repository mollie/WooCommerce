import { PAYMENT_TYPES } from '../../../shared/constants/paymentConstants';
import {
	validatePaymentItem,
	getPaymentConfig,
} from '../../../shared/utils/paymentUtils';
import { ApplePayUtils } from '../../../shared/utils/applePayUtils';
import { paymentStrategies } from './paymentStrategies';

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
 * @typedef {Object} RegistrationResult
 * @property {boolean} success - Whether the registration was successful
 * @property {string}  name    - The name of the payment method that was registered
 * @property {string}  type    - The type of registration ('regular' or 'express')
 * @property {string}  [error] - Error message if registration failed
 */

/**
 * Payment method registration logic
 * Handles the registration of payment methods with WooCommerce Blocks
 * @namespace PaymentRegistrar
 */

/**
 * Determines if a payment method can be registered based on validation and availability
 * Performs validation checks and Apple Pay specific availability checks
 * @param {PaymentItem} item - The payment method item to check
 * @return {boolean} True if the payment method can be registered, false otherwise
 * @example
 * const paymentMethod = {
 *     name: 'mollie_wc_gateway_applepay',
 *     title: 'Apple Pay',
 *     description: 'Pay with Apple Pay'
 * };
 * if (canRegisterPaymentMethod(paymentMethod)) {
 *     // Proceed with registration
 * }
 */
export const canRegisterPaymentMethod = ( item ) => {
	try {
		validatePaymentItem( item );
		const config = getPaymentConfig( item );

		// Apple Pay specific validation
		if (
			ApplePayUtils.isApplePayMethod( item ) &&
			config.requiresAppleSession
		) {
			return ApplePayUtils.canRegisterApplePay();
		}

		return true;
	} catch ( error ) {
		console.warn(
			`Mollie: Cannot register payment method ${ item?.name }:`,
			error.message
		);
		return false;
	}
};

/**
 * Registers a single payment method using the specified strategy
 * @param {PaymentItem}    item                         - The payment method item to register
 * @param {PaymentContext} context                      - The registration context containing WC blocks registry and dependencies
 * @param {string}         [type=PAYMENT_TYPES.REGULAR] - The payment type strategy to use ('regular' or 'express')
 * @return {Object} The registered payment method object from WooCommerce
 * @throws {Error} Throws error if payment type is unknown or registration fails
 * @example
 * try {
 *     const result = registerSinglePaymentMethod(
 *         { name: 'mollie_wc_gateway_ideal', title: 'iDEAL' },
 *         context,
 *         PAYMENT_TYPES.REGULAR
 *     );
 *     console.log('Payment method registered successfully');
 * } catch (error) {
 *     console.error('Registration failed:', error.message);
 * }
 */
export const registerSinglePaymentMethod = (
	item,
	context,
	type = PAYMENT_TYPES.REGULAR
) => {
	try {
		const strategy = paymentStrategies[ type ];

		if ( ! strategy ) {
			throw new Error( `Unknown payment type: ${ type }` );
		}

		return strategy( item, context );
	} catch ( error ) {
		console.error(
			`Mollie: Failed to register ${ type } ${ item.name }:`,
			error.message
		);
		throw error;
	}
};

/**
 * Registers a payment method with both regular and express variants if applicable
 * Attempts to register the regular payment method first, then the express variant if enabled
 * @param {PaymentItem}    item    - The payment method item to register
 * @param {PaymentContext} context - The registration context containing WC blocks registry and dependencies
 * @return {Array<RegistrationResult>} Array of registration results for each variant attempted
 * @example
 * const results = registerPaymentMethod(
 *     {
 *         name: 'mollie_wc_gateway_applepay',
 *         title: 'Apple Pay',
 *         config: { express: true }
 *     },
 *     context
 * );
 * // Returns: [
 * //   { success: true, name: 'mollie_wc_gateway_applepay', type: 'regular' },
 * //   { success: true, name: 'mollie_wc_gateway_applepay_express', type: 'express' }
 * // ]
 */
export const registerPaymentMethod = ( item, context ) => {
	const config = getPaymentConfig( item );
	const results = [];

	try {
		// Always register regular payment method first
		registerSinglePaymentMethod( item, context, PAYMENT_TYPES.REGULAR );
		results.push( { success: true, name: item.name, type: 'regular' } );

		// Register express variant if enabled and regular registration succeeded
		if ( config.express ) {
			registerSinglePaymentMethod( item, context, PAYMENT_TYPES.EXPRESS );
			results.push( {
				success: true,
				name: `${ item.name }_express`,
				type: 'express',
			} );
		}
	} catch ( error ) {
		results.push( {
			success: false,
			name: item.name,
			error: error.message,
		} );
	}

	return results;
};

/**
 * Registers all payment methods from the provided gateway data
 * Filters out invalid payment methods and registers all valid ones, logging the results
 * @param {Array<PaymentItem>} gatewayData - Array of payment method items to register
 * @param {PaymentContext}     context     - The registration context containing WC blocks registry and dependencies
 * @return {Array<RegistrationResult>} Array of all registration results (successful and failed)
 * @example
 * const gatewayData = [
 *     { name: 'mollie_wc_gateway_ideal', title: 'iDEAL' },
 *     { name: 'mollie_wc_gateway_applepay', title: 'Apple Pay', config: { express: true } }
 * ];
 * const results = registerAllPaymentMethods(gatewayData, context);
 * // Logs: "Mollie: Registered 3/3 payment method variants"
 * // Returns array with results for each registration attempt
 */
export const registerAllPaymentMethods = ( gatewayData, context ) => {
	const allResults = gatewayData
		.filter( canRegisterPaymentMethod )
		.flatMap( ( item ) => registerPaymentMethod( item, context ) );

	// Log registration results
	const successful = allResults.filter( ( r ) => r.success ).length;
	const failed = allResults.filter( ( r ) => ! r.success );

	console.log(
		`Mollie: Registered ${ successful }/${ allResults.length } payment method variants`
	);
	if ( failed.length > 0 ) {
		console.warn( 'Mollie: Failed registrations:', failed );
	}

	return allResults;
};
