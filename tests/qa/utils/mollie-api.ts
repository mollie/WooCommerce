/**
 * External dependencies
 */
import { RequestUtils, WooCommerceApi } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { MollieSettings } from '../resources';
import { urls, expect } from '.';

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

	setApiMethod = async (
		apiMethod: MollieSettings.ApiMethod = 'payment'
	) => {
		const url = urls.mollie.admin.settings.advanced;
		const wpnonce = await this.requestUtils.getPageNonce( url );
		const response = await this.requestUtils.request.post( url, {
			form: {
				'mollie-payments-for-woocommerce_debug': '1',
				// 'mollie-payments-for-woocommerce_order_status_cancelled_payments': 'pending',
				// 'mollie-payments-for-woocommerce_payment_locale': 'wp_locale',
				'mollie-payments-for-woocommerce_api_switch': apiMethod,
				// 'mollie-payments-for-woocommerce_api_payment_description': '{orderNumber}',
				// 'mollie-payments-for-woocommerce_gatewayFeeLabel': 'Gateway Fee',
				// 'mollie-payments-for-woocommerce_place_payment_onhold': 'immediate_capture',
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
	
	/**
	 * Triggers Subscription Renewal
	 *
	 * @param subscriptionId
	 */
	triggerSubscriptionRenewal = async ( subscriptionId: number ) => {
		const url = urls.wooCommerce.admin.subscriptions.edit + subscriptionId;
		const wpnonce = await this.requestUtils.getPageNonce( url );
		const formData = {
			_wpnonce: wpnonce,
			post_ID: subscriptionId,
			action: 'edit_order',
			wc_order_action: 'wcs_process_renewal',
		};
		const response = await this.requestUtils.submitPageForm(
			url,
			formData
		);
		return response.ok();
	};

	/**
	 * Get's renewal order IDs
	 * Utilizes the retry mechanism because after the renewal there appeared to be a delay
	 *
	 * @param subscriptionId
	 */
	getSubscriptionRenewalOrderIds = async (
		subscriptionId: number
	): Promise< number[] > => {
		let subscription = await this.getSubscription( subscriptionId );

		if ( ! subscription ) {
			console.error( `Subscription #${ subscriptionId } was not found.` );
			return [];
		}

		const MAX_RETRY_COUNT = 10;
		const RETRY_INTERVAL_MS = 1000;

		let retryCount = 0;
		let subscriptionMeta;

		do {
			subscriptionMeta = subscription.meta_data.find(
				( meta ) => meta.key === '_subscription_renewal_order_ids_cache'
			);

			if ( subscriptionMeta?.value?.length ) {
				return subscriptionMeta.value;
			}

			// Add a delay before making the getSubscription call
			await new Promise( ( resolve ) =>
				setTimeout( resolve, RETRY_INTERVAL_MS )
			);

			subscription = await this.getSubscription( subscriptionId );
			retryCount++;
		} while ( retryCount < MAX_RETRY_COUNT );

		console.error(
			`_subscription_renewal_order_ids_cache was not found in ${ MAX_RETRY_COUNT } sec.`
		);
		return [];
	};
}
