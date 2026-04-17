/**
 * Internal dependencies
 */
import { shopSettings, customers, ShopConfig } from '.';

const country = 'germany';

export const shopConfigDefault: ShopConfig = {
	enableClassicPages: false, // false = block cart and checkout (default), true = classic cart & checkout pages
	enableSubscriptionsPlugin: false, // WC Subscription plugin is deactivated
	settings: shopSettings[ country ], // WC general settings
	customer: customers[ country ], // registered customer
};

export const shopConfigClassic: ShopConfig = {
	...shopConfigDefault,
	enableClassicPages: true,
};
