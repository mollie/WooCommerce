/**
 * Internal dependencies
 */
import { shopSettings, customers } from '.';

const country = 'germany';

export const shopConfigDefault = {
	enableClassicPages: false, // false = block cart and checkout (default), true = classic cart & checkout pages
	wpDebugging: false, // WP Debugging plugin is deactivated
	subscription: false, // WC Subscription plugin is deactivated
	settings: shopSettings[ country ], // WC general settings
	customer: customers[ country ], // registered customer
};

export const shopConfigClassic = {
	...shopConfigDefault,
	enableClassicPages: true,
};

export const shopConfigGermany = {
	...shopConfigDefault,
	customer: customers.germany,
};

export const shopConfigUsa = {
	...shopConfigDefault,
	wpDebugging: true,
	settings: shopSettings.usa,
	customer: customers.usa,
};

export const shopConfigMexico = {
	...shopConfigDefault,
	settings: shopSettings.mexico,
	customer: customers.mexico,
};

const shopConfigSubscription = {
	// requireFinalConfirmation: false,
	subscription: true,
};

export const shopConfigSubscriptionGermany = {
	...shopConfigGermany,
	...shopConfigSubscription,
};

export const shopConfigSubscriptionUsa = {
	...shopConfigUsa,
	...shopConfigSubscription,
};
