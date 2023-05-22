const {expect} = require('@playwright/test');
const {test} = require('../../Shared/base-test');
const {resetSettings, insertAPIKeys} = require("../../Shared/mollieUtils");

test.describe('_Mollie Settings tab - General', () => {
    test.beforeEach(async ({page}) => {
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings');
    });


//TestId-C420150
    test('Validate that Mollie General section is displayed per UI design', async ({page}) => {
        await expect(await page.isVisible('text=Mollie Settings')).toBeTruthy();
        await expect(await page.isVisible('text=General')).toBeTruthy();
        await expect(await page.isVisible('text=Live API Key')).toBeTruthy();
    });
//TestId-C3333
    test('Validate that the ecommerce admin have access to Documentation/Support through the Setting page', async ({page, context}) => {
        await page.click('text=Plugin Documentation');
        await expect(page.url()).toBe('https://github.com/mollie/WooCommerce/wiki');
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings');
        await page.click('text=Contact Support');
        await expect(page.url()).toBe('https://www.mollie.com/contact/merchants');
    });

//TestId-C3511
    test('Validate an error message is returned when the test key is not valid/empty', async ({page}) => {
        await resetSettings(page);
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings');
        await expect(await page.isVisible('text=Communicating with Mollie failed')).toBeTruthy();
    });
//TestId-C3510
    test('Validate that test/live keys are valid', async ({page}) => {
        await insertAPIKeys(page);
        expect(await page.isVisible('text=Connected')).toBeTruthy();
    });


//TestId-C3330
    test('Validate that the ecommerce admin can activate debug mode', async ({page}) => {
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings');
        await expect(await page.isVisible('text=Enable test mode')).toBeTruthy();
        //expect enable test mode checkbox to be checked
        await expect(await page.getByRole('group', { name: 'Enable test mode' }).locator('label')).toBeChecked();
    });



//TestId-C3507
    test.skip('Validate the connection to Mollie OAuth is working as expected when consent is approved', async ({page}) => {
        // Your code here...
    });


//TestId-C3508
    test.skip('Validate no connection is created using Mollie OAuth when consent is denied', async ({page}) => {
        // Your code here...
    });


//TestId-C3509
    test.skip('Validate that the connection through hosted onboarding is working', async ({page}) => {
        // Your code here...
    });

//TestId-C3512
    test.skip('Validate all payment methods are displayed and only activate the ones approved in Mollie dashboard', async ({page}) => {
        // Your code here...
    });


//TestId-C3513
    test.skip('Validate when a payment method is activated in the ecommerce platform that this one also gets activated in Mollie dashboard', async ({page}) => {
        // Your code here...
    });


//TestId-C3514
    test.skip('Validate that only the activated payment methods in Mollie dashboard are displayed in the ecommerce platform', async ({page}) => {
        // Your code here...
    });

});
