/**
 * Internal dependencies
 */
import { mollieConfigGeneral, MollieSettings } from '../../resources';
import { urls } from '../urls';
import { MollieSettingsPage } from './mollie-settings-page';

export class MollieSettingsApiKeys extends MollieSettingsPage {
	url = urls.mollie.admin.settings.home;
	tabText = 'API Keys';
	headingText = 'Mollie API Keys';

	// Locators
	successfullyConnectedWithTestApiText = () =>
		this.page.getByText( 'Successfully connected with Test API' );
	failedToConnectToMollieApiText = () =>
		this.page.getByText(
			'Failed to connect to Mollie API - check your API keys'
		);
	molliePaymentModeSelect = () =>
		this.page.locator(
			'#mollie-payments-for-woocommerce_test_mode_enabled'
		);
	liveApiKeyInput = () =>
		this.page.locator( '#mollie-payments-for-woocommerce_live_api_key' );
	testApiKeyInput = () =>
		this.page.locator( '#mollie-payments-for-woocommerce_test_api_key' );

	// Actions

	/**
	 * Setup Mollie General settings
	 *
	 * @param data
	 */
	setup = async ( data: MollieSettings.ApiKeys ) => {
		if ( data.testModeEnabled !== undefined ) {
			await this.molliePaymentModeSelect().selectOption(
				data.testModeEnabled
			);
		}

		if ( data.liveApiKey !== undefined ) {
			await this.liveApiKeyInput().fill( data.liveApiKey );
		}

		if ( data.testApiKey !== undefined ) {
			await this.testApiKeyInput().fill( data.testApiKey );
		}
	};

	setApiKeys = async (
		data: MollieSettings.ApiKeys = mollieConfigGeneral.default
	) => {
		await this.setup( data );
	};

	// Assertions
}
