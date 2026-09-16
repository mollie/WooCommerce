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

// Billink only works with an NL merchant, so its shop must run under NL general/settings/customer settings
export const shopConfigNetherlands: ShopConfig = {
	...shopConfigDefault,
	settings: shopSettings.netherlands,
	customer: customers.netherlands,
};

export const shopConfigNetherlandsClassic: ShopConfig = {
	...shopConfigNetherlands,
	enableClassicPages: true,
};
