// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {insertAPIKeys, resetSettings} = require('../Shared/mollieUtils');
const {sharedUrl: {mollieSettingsTab}} = require('../Shared/sharedUrl');
test.describe('Should show general settings', () => {
    test('Should show empty and disconnected', async ({ page}) => {
        await resetSettings(page);
        await page.goto(mollieSettingsTab);
        await expect(page.getByText(/API keys missing/)).toBeVisible();
    });
    test('Should connect when API key is present', async ({ page , gateways}) => {
        await page.goto(mollieSettingsTab);
        await insertAPIKeys(page);
        await expect(page.getByText(/Mollie status: Connected/)).toBeVisible();
        for ( const gatewayName in gateways ){
            const title = gateways[gatewayName].defaultTitle
            await expect(page.locator(`text=${title} enabled edit >> span`)).toBeVisible();
        }
    });
});
