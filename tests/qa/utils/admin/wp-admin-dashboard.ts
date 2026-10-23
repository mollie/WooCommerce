/**
 * External dependencies
 */
import { WpPage } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { urls } from '../urls';

export class WpAdminDashboard extends WpPage {
	url = urls.admin.home;

	// Locators
	adminNotice = ( text: string ) =>
		this.page.locator( '.notice', { hasText: text } );

	// Actions

	// Assertions
}
