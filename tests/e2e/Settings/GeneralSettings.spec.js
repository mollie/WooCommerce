// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {insertAPIKeys, resetSettings} = require('../Shared/mollieUtils');

test.describe('Should show general settings', () => {
    test.beforeAll(async ({browser }) => {
        const page = await browser.newPage();
        await resetSettings(page);
    });
    test('Should show empty and disconnected', async ({ page , gateways}) => {
        // Go to settings
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section');
        await expect(page.locator('text=No API key provided. Please set your Mollie API keys below.')).toBeVisible();
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
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section');
        await insertAPIKeys(page);
        await expect(page.locator('text=Mollie status: Connected')).toBeVisible();
        for ( const gatewayName in gateways ){
            const title = gateways[gatewayName].defaultTitle
            await expect(page.locator(`text=${title} enabled edit >> span`)).toBeVisible();
        }
    });
});
