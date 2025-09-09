export const PAYMENT_TYPES = {
	REGULAR: 'regular',
	EXPRESS: 'express',
};

export const DEFAULT_CONFIG = {
	express: false,
	requiresAppleSession: false,
	supports: { features: [ 'products' ] },
};

export const APPLE_PAY_GATEWAY_NAME = 'mollie_wc_gateway_applepay';
