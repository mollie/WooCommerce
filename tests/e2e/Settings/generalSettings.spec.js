// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

const { loginAdmin } = require('../Shared/wpUtils');
const {insertAPIKeys, resetSettings} = require('../Shared/mollieUtils');

test.describe('Should show general settings', () => {
    test.beforeAll(async ({browser }) => {
        const page = await browser.newPage();
        //login as Admin
        await loginAdmin(page);
        await resetSettings(page);
        await insertAPIKeys(page);
    });
    test('Should show empty and disconnected', async ({ page , gateways}) => {
        // Go to settings
        await loginAdmin(page);
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section');
        await expect(page.locator('text=No API key provided. Please set your Mollie API keys below.')).toBeVisible();

        for ( const key in gateways ){
            let testedGateway = gateways[key]
            //check default icon with a locator that has disabled and activate
            const url = await page.$eval(`text=${testedGateway.defaultTitle} disabled activate >> img`, img => img.src);
            await expect(url).toEqual(`${process.env.E2E_URL_TESTSITE}/wp-content/plugins/${process.env.E2E_TESTPACKAGE}//public/images/${testedGateway.id}.svg`)
        }
        //fill with API keys
        await page.locator('[placeholder="Live API key should start with live_"]').fill('live_HFGW8amkfcb7dvrasy8WdUNRNhscxa');
        await page.locator('input[name="mollie-payments-for-woocommerce_test_mode_enabled"]').check();
        await page.locator('[placeholder="Test API key should start with test_"]').fill('test_NgHd7vSyPSpEyuTEwhvsxdjsgVG4SV');
        // Click text=Save changes
        await Promise.all([
            page.waitForNavigation(/*{ url: 'https://cmaymo.emp.pluginpsyde.com/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section' }*/),
            page.locator('text=Save changes').click()
        ]);
        await expect(page.locator('text=Mollie status: Connected')).toBeVisible();
        for ( const key in gateways ){
            let testedGateway = gateways[key]
            await expect(page.locator(`text=${testedGateway.defaultTitle} enabled edit >> span`)).toBeVisible();
        }
    });
});









