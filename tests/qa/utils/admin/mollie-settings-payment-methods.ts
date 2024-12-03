/**
 * External dependencies
 */
import { WooCommerceAdminPage } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { urls } from '../urls';

export class MollieSettingsPaymentMethods extends WooCommerceAdminPage {
	url = urls.mollie.admin.settings.paymentMethods;
	tabText: 'Payment methods';
	headingText: string;

	// Locators

	// Actions

	// Assertions
}
