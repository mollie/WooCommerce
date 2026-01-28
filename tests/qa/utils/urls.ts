/**
 * External dependencies
 */
import {
	urls as urlsBase,
	wooCommerceUrls,
} from '@inpsyde/playwright-utils/build';

export const urls = {
	...urlsBase.frontend,
	...wooCommerceUrls.frontend,
	admin: urlsBase.admin,
	wooCommerce: {
		admin: wooCommerceUrls.admin,
	},
	mollie: {
		admin: {
			settings: {
				home: './wp-admin/admin.php?page=wc-settings&tab=mollie_settings',
				apiKeys:
					'./wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=mollie_api_keys',
				paymentMethods:
					'./wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=mollie_payment_methods',
				advanced:
					'./wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=mollie_advanced',
				gateway:
					'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=mollie_wc_gateway_',
			},
		},
		hostedCheckout: 'https://www.mollie.com/checkout',
	},
};
