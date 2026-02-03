/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

test( 'C420152 | Validate that Mollie Advanced section is displayed per UI design', async ( {
	mollieSettingsAdvanced,
} ) => {
	await mollieSettingsAdvanced.visit();
	await expect(
		mollieSettingsAdvanced.heading(),
		'Assert heading is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.debugLogCheckbox(),
		'Assert debug log checkbox is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.orderStatusCancelledPaymentSelect(),
		'Assert order status cancelled payment select is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.paymentScreenLanguageSelect(),
		'Assert payment screen language select is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.storeCustomerDetailsAtMollieCheckbox(),
		'Assert store customer details at Mollie checkbox is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.selectAPIMethodSelect(),
		'Assert select API method select is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionInput(),
		'Assert API payment description input is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton( '{orderNumber}' ),
		'Assert API payment description {orderNumber} button is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton( '{storeName}' ),
		'Assert API payment description {storeName} button is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton(
			'{customer.firstname}'
		),
		'Assert API payment description {customer.firstname} button is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton(
			'{customer.lastname}'
		),
		'Assert API payment description {customer.lastname} button is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.apiPaymentDescriptionButton(
			'{customer.company}'
		),
		'Assert API payment description {customer.company} button is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.surchargeGatewayFeeLabelInput(),
		'Assert surcharge gateway fee label input is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.removeMollieDataFromDatabaseOnUninstall(),
		'Assert remove Mollie data from database on uninstall checkbox is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.clearNowLink(),
		'Assert clear now link is visible'
	).toBeVisible();
	await expect(
		mollieSettingsAdvanced.placingPaymentsOnHoldSelect(),
		'Assert placing payments on hold select is visible'
	).toBeVisible();
} );
