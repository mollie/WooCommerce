/**
 * External dependencies
 */
import { WooCommerceApi } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { MollieSettings } from '../resources';

export class MollieApi extends WooCommerceApi {
	updateMollieGateway = async (
		gatewaySlug: string,
		settings: MollieSettings.Gateway
	) => {
		const requestData = { settings };
		const response = await this.wcRequest(
			'put',
			`payment_gateways/mollie_wc_gateway_${ gatewaySlug }`,
			requestData
		);
		return response;
	};
}
