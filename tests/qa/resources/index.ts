export {
	shopSettings,
	shippingZones,
	flatRate,
	freeShipping,
	guests,
	customers,
	taxSettings,
	coupons,
	orders,
} from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';

export * from './products';
export * from './gateways';
export * from './cards';
export * from './mollie-config';
export * from './woocommerce-config';
export * from './types';

export { default as molliePlugin } from './mollie-plugin';
export { default as disableNoncePlugin } from './disable-nonce-plugin';
export { default as subscriptionsPlugin } from './woocommerce-subscriptions-plugin';
export { default as disableWcSetupWizard } from './disable-wc-setup-wizard-plugin.json';
export { default as disableGutenbergWelcomeGuide } from './disable-gutenberg-welcome-guide-plugin.json';
export { default as featureFlagsPlugin } from './feature-flags-plugin';
export { default as thirdPartyFeesPlugin } from './third-party-fees-plugin';
