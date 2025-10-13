/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

test( 'C420152 | Validate that Mollie Advanced section is displayed per UI design', async ( {
	mollieSettingsAdvanced,
} ) => {
	await mollieSettingsAdvanced.visit();
	await expect( mollieSettingsAdvanced.heading() ).toBeVisible();
	await expect( mollieSettingsAdvanced.debugLogCheckbox() ).toBeVisible();
	await expect(
		mollieSettingsAdvanced.orderStatusCancelledPaymentSelect()
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.paymentScreenLanguageSelect()
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.storeCustomerDetailsAtMollieCheckbox()
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.selectAPIMethodSelect()
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionInput()
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton( '{orderNumber}' )
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton( '{storeName}' )
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton(
			'{customer.firstname}'
		)
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton(
			'{customer.lastname}'
		)
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton(
			'{customer.company}'
		)
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.surchargeGatewayFeeLabelInput()
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.removeMollieDataFromDatabaseOnUninstall()
	).toBeVisible();
	await expect( mollieSettingsAdvanced.clearNowLink() ).toBeVisible();
	await expect(
		mollieSettingsAdvanced.placingPaymentsOnHoldSelect()
	).toBeVisible();
} );
