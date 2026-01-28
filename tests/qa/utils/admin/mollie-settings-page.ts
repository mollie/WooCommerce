/**
 * External dependencies
 */
import { WooCommerceAdminPage } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { urls } from '../urls';

export class MollieSettingsPage extends WooCommerceAdminPage {
	url = urls.mollie.admin.settings.home;
	tabText: string;
	headingText: string;

	// Locators
	heading = () =>
		this.page.getByRole( 'heading', { name: String( this.headingText ) } );
	molliePluginDocumentationButton = () =>
		this.page.getByRole( 'link', { name: 'Mollie Plugin Documentation' } );
	contactMollieSupportButton = () =>
		this.page.getByRole( 'link', { name: 'Contact Mollie Support' } );

	// Actions

	// Assertions
}
