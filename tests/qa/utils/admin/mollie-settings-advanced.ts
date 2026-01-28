/**
 * Internal dependencies
 */
import { MollieSettingsPage } from './mollie-settings-page';
import { MollieSettings } from '../../resources';
import { urls } from '../urls';

export class MollieSettingsAdvanced extends MollieSettingsPage {
	url = urls.mollie.admin.settings.advanced;
	tabText = 'Advanced settings';
	headingText = 'Mollie advanced settings';

	// Locators
	debugLogCheckbox = () =>
		this.page.locator( '#mollie-payments-for-woocommerce_debug' );
	orderStatusCancelledPaymentSelect = () =>
		this.page.locator(
			'#mollie-payments-for-woocommerce_order_status_cancelled_payments'
		);
	paymentScreenLanguageSelect = () =>
		this.page.locator( '#mollie-payments-for-woocommerce_payment_locale' );
	storeCustomerDetailsAtMollieCheckbox = () =>
		this.page.locator(
			'#mollie-payments-for-woocommerce_customer_details'
		);
	selectAPIMethodSelect = () =>
		this.page.locator( '#mollie-payments-for-woocommerce_api_switch' );
	apiPaymentDescriptionInput = () =>
		this.page.locator(
			'#mollie-payments-for-woocommerce_api_payment_description'
		);
	apiPaymentDescriptionButton = (
		name: MollieSettings.ApiPaymentDescription
	) => this.page.locator( `button[data-tag="${ name }"]` );
	surchargeGatewayFeeLabelInput = () =>
		this.page.locator( '#mollie-payments-for-woocommerce_gatewayFeeLabel' );
	removeMollieDataFromDatabaseOnUninstall = () =>
		this.page.locator(
			'#mollie-payments-for-woocommerce_removeOptionsAndTransients'
		);
	clearNowLink = () =>
		this.page.locator(
			'label[for="mollie-payments-for-woocommerce_removeOptionsAndTransients"] a'
		);
	placingPaymentsOnHoldSelect = () =>
		this.page.locator(
			'#mollie-payments-for-woocommerce_place_payment_onhold'
		);

	// Actions

	/**
	 * Setup Mollie Advanced settings
	 *
	 * @param data
	 */
	setup = async ( data: MollieSettings.Advanced ) => {
		if ( data.debugLogEnabled !== undefined ) {
			await this.debugLogCheckbox().setChecked( data.debugLogEnabled );
		}

		if ( data.orderStatusCancelledPayments !== undefined ) {
			await this.orderStatusCancelledPaymentSelect().selectOption(
				data.orderStatusCancelledPayments
			);
		}

		if ( data.paymentLocale !== undefined ) {
			await this.paymentScreenLanguageSelect().selectOption(
				data.paymentLocale
			);
		}

		if ( data.customerDetailsEnabled !== undefined ) {
			await this.storeCustomerDetailsAtMollieCheckbox().setChecked(
				data.customerDetailsEnabled
			);
		}

		if ( data.apiMethod !== undefined ) {
			await this.selectAPIMethodSelect().selectOption( data.apiMethod );
		}

		if ( data.apiPaymentDescription !== undefined ) {
			await this.apiPaymentDescriptionInput().clear();
			await this.apiPaymentDescriptionButton(
				data.apiPaymentDescription
			).click();
		}

		if ( data.gatewayFeeLabel !== undefined ) {
			await this.surchargeGatewayFeeLabelInput().fill(
				data.gatewayFeeLabel
			);
		}

		if ( data.removeOptionsAndTransientsEnabled !== undefined ) {
			await this.removeMollieDataFromDatabaseOnUninstall().setChecked(
				data.removeOptionsAndTransientsEnabled
			);
		}

		if ( data.placePaymentOnhold !== undefined ) {
			await this.placingPaymentsOnHoldSelect().selectOption(
				data.placePaymentOnhold
			);
		}
	};

	cleanDb = async () => {
		await this.clearNowLink().click();
		await this.page.waitForLoadState();
	};

	// Assertions
}
