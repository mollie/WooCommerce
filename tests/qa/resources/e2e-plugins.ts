const filePath = './tests/qa/resources/files';

export const disableGutenbergWelcomeGuidePlugin = {
	name: 'Disable Gutenberg Welcome Guide',
	slug: 'disable-gutenberg-welcome-guide',
	zipFilePath: `${ filePath }/disable-gutenberg-welcome-guide.zip`,
};

export const disableNonceCheckPlugin = {
	name: 'Disable nonce check',
	slug: 'disable-nonce-check',
	zipFilePath: `${ filePath }/disable-nonce-check.zip`,
};

export const disableWcSetupWizardPlugin = {
	name: 'Disable WC Setup Wizard',
	slug: 'disable-wc-setup-wizard',
	zipFilePath: `${ filePath }/disable-wc-setup-wizard.zip`,
};

export const featureFlagsPlugin = {
	name: 'Feature Flags',
	slug: 'feature-flags',
	zipFilePath: `${ filePath }/feature-flags.zip`,
};

export const woocommerceSubscriptionsPlugin = {
	name: 'WooCommerce Subscriptions',
	slug: 'woocommerce-subscriptions',
	zipFilePath: `${ filePath }/woocommerce-subscriptions.zip`,
};

export const molliePlugin = {
	name: 'Mollie Payments for WooCommerce',
	slug: 'mollie-payments-for-woocommerce',
	zipFilePath: `${ filePath }/mollie-payments-for-woocommerce.zip`,
};

export const germanizedPlugin = {
	name: 'Germanized for WooCommerce',
	slug: 'germanized-for-woocommerce',
	zipFilePath: `${ filePath }/germanized-for-woocommerce.zip`,
};

export const germanizedProPlugin = {
	name: 'Germanized for WooCommerce Pro',
	slug: 'germanized-for-woocommerce-pro',
	zipFilePath: `${ filePath }/germanized-for-woocommerce-pro.zip`,
};

export const enableBizumPlugin = {
	name: 'Enable Bizum',
	slug: 'enable-bizum',
	zipFilePath: `${ filePath }/enable-bizum.zip`,
};

/**
 * QA-only probe for PIWOO-890 (order-received page double-counted by
 * tracking tools). See tests/08-bug-verification/piwoo-890.spec.ts.
 */
export const piwoo890OrderReceivedProbePlugin = {
	name: 'PIWOO-890 Order Received Probe',
	slug: 'piwoo-890-order-received-probe',
	zipFilePath: `${ filePath }/piwoo-890-order-received-probe.zip`,
};

// QA-only helpers for the manual 8.2.0 Express tests, installed inactive by setup:express-helper-plugins;

// Puts one product line below zero (PIWOO-842 code path), EXP-20 and EXP-46
export const negativeLineItemPlugin = {
	name: 'Negative Line Item',
	slug: 'negative-line-item',
	zipFilePath: `${ filePath }/negative-line-item.zip`,
};

// Negative fee line in the Express session, EXP-56
export const negative001FeePlugin = {
	name: 'Negative 0.01 Fee',
	// requestUtils derives the slug from the plugin name, not the folder
	slug: 'negative-0-01-fee',
	zipFilePath: `${ filePath }/negative-001-fee.zip`,
};

// Positive fee lines (5 and 1 EUR) in the Express session, EXP-55
export const thirdPartyFeesPlugin = {
	name: 'Third Party Fees',
	slug: 'third-party-fees',
	zipFilePath: `${ filePath }/third-party-fees.zip`,
};
