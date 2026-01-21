/**
 * External dependencies
 */
import { RequestUtils, WooCommerceApi } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { MollieSettings } from '../resources';
import { urls, expect } from '.';

/**
 * Class for Mollie Plugin API
 */
export class MollieApi extends WooCommerceApi {
	requestUtils: RequestUtils;

	constructor( { request, requestUtils } ) {
		super( { request } );
		this.requestUtils = requestUtils;
	}

	setMollieApiKeys = async ( data: MollieSettings.ApiKeys ) => {
		const { testModeEnabled, liveApiKey, testApiKey } = data;
		const url = urls.mollie.admin.settings.apiKeys;
		const wpnonce = await this.requestUtils.getPageNonce( url );
		const response = await this.requestUtils.request.post( url, {
			form: {
				'mollie-payments-for-woocommerce_test_mode_enabled':
					testModeEnabled,
				'mollie-payments-for-woocommerce_live_api_key': liveApiKey,
				'mollie-payments-for-woocommerce_test_api_key': testApiKey,
				save: 'Save changes',
				_wpnonce: wpnonce,
			},
		} );
		await expect( response ).toBeOK();
		return response;
	};

	cleanMollieDb = async () => {
		const url = urls.mollie.admin.settings.advanced;
		const nonce = await this.requestUtils.getRegexMatchValueOnPage(
			url,
			/nonce_mollie_cleanDb=([^"&]+)/
		);
		const response = await this.requestUtils.request.get( url, {
			params: {
				'cleanDB-mollie': 1,
				nonce_mollie_cleanDb: nonce,
			},
		} );
		await expect( response ).toBeOK();
		return response;
	};

	setAdvancedSettings = async (
		data: MollieSettings.Advanced,
	) => {
		const {
			debugLogEnabled,
			orderStatusCancelledPayments,
			paymentLocale,
			customerDetailsEnabled,
			apiMethod,
			apiPaymentDescription,
			gatewayFeeLabel,
			removeOptionsAndTransientsEnabled,
			placePaymentOnhold,
		}: MollieSettings.Advanced = data;

		const url = urls.mollie.admin.settings.advanced;
		const wpnonce = await this.requestUtils.getPageNonce( url );
		const response = await this.requestUtils.request.post( url, {
			form: {
				'mollie-payments-for-woocommerce_debug':
					debugLogEnabled !== false ? '1' : '0', // default = 1, if undefined => treated as 0
				'mollie-payments-for-woocommerce_order_status_cancelled_payments':
					orderStatusCancelledPayments || 'pending',
				'mollie-payments-for-woocommerce_payment_locale':
					paymentLocale || 'wp_locale',
				'mollie-payments-for-woocommerce_customer_details':
					customerDetailsEnabled !== false ? '1' : '0', // default = 1, if undefined => treated as 0
				'mollie-payments-for-woocommerce_api_switch':
					apiMethod || 'payment',
				'mollie-payments-for-woocommerce_api_payment_description':
					apiPaymentDescription || '{orderNumber}',
				'mollie-payments-for-woocommerce_gatewayFeeLabel':
					gatewayFeeLabel || 'Gateway Fee',
				'mollie-payments-for-woocommerce_removeOptionsAndTransients':
					removeOptionsAndTransientsEnabled === true ? '1' : '0', // default = 0, if undefined => treated as 0
				'mollie-payments-for-woocommerce_place_payment_onhold':
					placePaymentOnhold || 'immediate_capture',
				save: 'Save changes',
				_wpnonce: wpnonce,
			},
		} );
		await expect( response ).toBeOK();
		return response;
	};

	updateMollieGateway = async (
		gatewaySlug: string,
		data: MollieSettings.Gateway
	) => {
		const response = await this.wcRequest(
			'put',
			`payment_gateways/mollie_wc_gateway_${ gatewaySlug }`,
			{ settings: data }
		);
		return response;
	};
}
