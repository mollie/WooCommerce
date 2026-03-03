/**
 * Internal dependencies
 */
import { WooCommerceUtils as WooCommerceUtilsBase, wooCommerceUrls, urls } from '@inpsyde/playwright-utils/build';

export class WooCommerceUtils extends WooCommerceUtilsBase {
	createApiKeys = async () => {
		const security = await this.requestUtils.getRegexMatchValueOnPage(
			wooCommerceUrls.admin.settings.createApiKey,
			/"update_api_nonce":"([^"]+)"/
		);
		
		console.log( 'Security nonce:', security );
		
		const formData = {
			action: 'woocommerce_update_api_key',
			key_id: '0',
			user: '1',
			security,
			description: `Test_${ Date.now() }`,
			permissions: 'read_write',
		};
		const response = await this.requestUtils.submitPageForm(
			urls.admin.ajax,
			formData
		);

		console.log( 'Response status:', response.status() );
		const body = await response.text();
		console.log( 'Response body:', body.substring( 0, 500 ) );

		if ( ! response.ok() ) {
			throw new Error( `Failed to create WC API Keys. Status: ${ response.status() }` );
		}

		const data = ( await response.json() ).data;
		const apiKeys = {
			WC_API_KEY: data.consumer_key,
			WC_API_SECRET: data.consumer_secret,
		};
		return apiKeys;
	};
}
