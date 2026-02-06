/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';

test.beforeAll( async ( { utils } ) => {
	await utils.installAndActivateMollie();
} );

test( 'C420150 | Validate that Mollie API Keys section is displayed per UI design', async ( {
	mollieSettingsApiKeys,
} ) => {
	await mollieSettingsApiKeys.visit();
	await expect(
		mollieSettingsApiKeys.heading(),
		'Assert heading is visible'
	).toBeVisible();
	await expect(
		mollieSettingsApiKeys.molliePaymentModeSelect(),
		'Assert mollie payment mode select is visible'
	).toBeVisible();
	await expect(
		mollieSettingsApiKeys.liveApiKeyInput(),
		'Assert live API key input is visible'
	).toBeVisible();
	await expect(
		mollieSettingsApiKeys.molliePaymentModeSelect(),
		'Assert mollie payment mode select is visible'
	).toBeVisible();
	await expect(
		mollieSettingsApiKeys.testApiKeyInput(),
		'Assert test API key input is visible'
	).toBeVisible();
} );

test( 'C3333 | Validate that the ecommerce admin have access to Documentation/Support through the Setting page', async ( {
	mollieSettingsApiKeys,
	request,
	page,
} ) => {
	await mollieSettingsApiKeys.visit();
	const contactMollieSupportButton =
		mollieSettingsApiKeys.contactMollieSupportButton();
	const contactSupportHref = await contactMollieSupportButton.getAttribute(
		'href'
	);
	await expect(
		contactSupportHref,
		'Assert contact support href is correct'
	).toEqual( 'https://www.mollie.com/contact/merchants' );
	await expect(
		await request.get( contactSupportHref ),
		'Assert contact support request is OK'
	).toBeOK();

	const molliePluginDocumentationButton =
		mollieSettingsApiKeys.molliePluginDocumentationButton();
	const pluginDocumentationHref =
		await molliePluginDocumentationButton.getAttribute( 'href' );
	await expect(
		pluginDocumentationHref,
		'Assert plugin documentation href is correct'
	).toEqual( 'https://docs.mollie.com/docs/woo-get-started' );
	await page.goto( pluginDocumentationHref );
	await expect(
		page,
		'Assert plugin documentation page is loaded'
	).toHaveURL( 'https://docs.mollie.com/docs/woo-get-started' );
} );
