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

export const shopConfigGermany: ShopConfig = {
	...shopConfigDefault,
	customer: customers.germany,
};

export const shopConfigUsa: ShopConfig = {
	...shopConfigDefault,
	settings: shopSettings.usa,
	customer: customers.usa,
};

export const shopConfigMexico: ShopConfig = {
	...shopConfigDefault,
	settings: shopSettings.mexico,
	customer: customers.mexico,
};

const shopConfigSubscription: ShopConfig = {
	// requireFinalConfirmation: false,
	enableSubscriptionsPlugin: true,
};

export const shopConfigSubscriptionGermany: ShopConfig = {
	...shopConfigGermany,
	...shopConfigSubscription,
};

export const shopConfigSubscriptionUsa: ShopConfig = {
	...shopConfigUsa,
	...shopConfigSubscription,
};
