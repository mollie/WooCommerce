/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';

test.beforeAll( async ( { utils } ) => {
	await utils.installActivateMollie();
} );

test( 'C420150 | Validate that Mollie API Keys section is displayed per UI design', async ( {
	mollieSettingsApiKeys,
} ) => {
	await mollieSettingsApiKeys.visit();
	await expect( mollieSettingsApiKeys.heading() ).toBeVisible();
	await expect(
		mollieSettingsApiKeys.molliePaymentModeSelect()
	).toBeVisible();
	await expect( mollieSettingsApiKeys.liveApiKeyInput() ).toBeVisible();
	await expect(
		mollieSettingsApiKeys.molliePaymentModeSelect()
	).toBeVisible();
	await expect( mollieSettingsApiKeys.testApiKeyInput() ).toBeVisible();
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
	await expect( contactSupportHref ).toEqual(
		'https://www.mollie.com/contact/merchants'
	);
	await expect( await request.get( contactSupportHref ) ).toBeOK();

	const molliePluginDocumentationButton =
		mollieSettingsApiKeys.molliePluginDocumentationButton();
	const pluginDocumentationHref =
		await molliePluginDocumentationButton.getAttribute( 'href' );
	await expect( pluginDocumentationHref ).toEqual(
		'https://help.mollie.com/hc/en-us/sections/12858723658130-Mollie-for-WooCommerce'
	);
	await page.goto( pluginDocumentationHref );
	await expect( page ).toHaveURL( /molliehelp\.zendesk\.com\/auth/ );
} );

test.fixme(
	'C3330 | Validate that the ecommerce admin can activate debug mode',
	async ( { mollieSettingsApiKeys } ) => {
		await mollieSettingsApiKeys.visit();
		await mollieSettingsApiKeys.molliePaymentModeSelect().uncheck();
		await expect(
			mollieSettingsApiKeys.testApiKeyInput()
		).not.toBeVisible();
		await mollieSettingsApiKeys.molliePaymentModeSelect().check();
		await expect( mollieSettingsApiKeys.testApiKeyInput() ).toBeVisible();
	}
);
