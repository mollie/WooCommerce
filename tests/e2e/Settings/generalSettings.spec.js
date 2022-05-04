// @ts-check
const { test, expect } = require('@playwright/test');
const PRODUCTS = {
    'simple': {
        'name': 'simple_taxes',
        'price': '24,33€'
    }
  }
const GATEWAYS = {
    'banktransfer': {
            'id' : 'banktransfer',
            'defaultTitle' : 'Bank Transfer',
            'settingsDescription' : '',
            'defaultDescription' : '',
            'paymentFields' : false,
            'instructions' : true,
            'supports' : [
                        'products',
                        'refunds',
                        ],
            'filtersOnBuild' : true,
            'confirmationDelayed' : true,
            'SEPA' : false,
            'customRedirect' : true,
    }
}
/**
 * @param {import('@playwright/test').Page} page
 */
async function loginAdmin(page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-login.php');
    await page.locator('#user_pass').fill(process.env.ADMIN_PASS);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log in').click()
    ]);
}
async function resetSettings(page){
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await Promise.all([
             page.waitForNavigation(),
             await page.locator('text=clear now').click()
         ]);
}

async function insertAPIKeys(page){
    await page.goto('https://cmaymo.emp.pluginpsyde.com/wp-admin/admin.php?page=wc-settings&tab=mollie_settings');
    await page.locator(`input[name="mollie-payments-for-woocommerce_live_api_key"]`).fill(process.env.MOLLIE_LIVE_API_KEY);
    await page.locator(`input[name="mollie-payments-for-woocommerce_test_mode_enabled"]`).check();
    await page.locator(`input[name="mollie-payments-for-woocommerce_test_api_key"]`).fill(process.env.MOLLIE_TEST_API_KEY);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

test.describe('Should show general settings', () => {
    test.beforeAll(async ({browser }) => {
        const page = await browser.newPage();
        //login as Admin
        await loginAdmin(page);
        await resetSettings(page);



    });
    test('Should show empty and disconnected', async ({ page }) => {
        // Go to settings
        await loginAdmin(page);
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section');
        await expect(page.locator('text=No API key provided. Please set your Mollie API keys below.')).toBeVisible();
        
        for ( const key in GATEWAYS ){
            let testedGateway = GATEWAYS[key]
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
        for ( const key in GATEWAYS ){
            let testedGateway = GATEWAYS[key]
            await expect(page.locator(`text=${testedGateway.defaultTitle} enabled edit >> span`)).toBeVisible();
        }  
    });
});









