// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {insertAPIKeys, resetSettings} = require('../Shared/mollieUtils');
const {sharedUrl: {settingsRoot}} = require('../Shared/sharedUrl');
test.describe('Should show general settings', () => {
    test.beforeAll(async ({browser , baseURL}) => {
        const page = await browser.newPage({ baseURL: baseURL, extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'}});
        await resetSettings(page);
    });
    test('Should show empty and disconnected', async ({ page , gateways}) => {
        await page.goto(settingsRoot);
        await expect(page.locator('text=API keys missing.')).toBeVisible();
        for ( const gatewayName in gateways ){
            //check default icon with a locator that has disabled and activate
            const title = gateways[gatewayName].defaultTitle
            const id = gateways[gatewayName].id
            const url = await page.$eval(`text=${title} disabled activate >> img`, img => img.src);
            await expect(url).toContain(`/public/images/${id}.svg`)
        }
    });
    test('Should connect when API key is present', async ({ page , gateways}) => {
        // Go to settings
        await page.goto(settingsRoot);
        await insertAPIKeys(page);
        await expect(page.locator('text=Mollie status: Connected')).toBeVisible();
        for ( const gatewayName in gateways ){
            const title = gateways[gatewayName].defaultTitle
            await expect(page.locator(`text=${title} enabled edit >> span`)).toBeVisible();
        }
    });
});
