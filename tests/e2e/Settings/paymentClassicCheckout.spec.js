// @ts-check
const { test, expect } = require('@playwright/test');

const GATEWAYS = {
    'banktransfer': {
        'title': 'Bank Transfer',
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
test.describe('Should show payment settings on classic checkout', () => {
    test.beforeAll(async ({ page }) => {
        //login as Admin
        await loginAdmin(page);
        //reset settings
        //go to checkout
    });
    test('Should show default settings', async ({ page }) => {
        for ( const key in GATEWAYS){
            let testedGateway = GATEWAYS[key]
            //check default title
            //check default icon
            //check default description
            //check issuers dropdown show
            //shown with random available country
            //no fee added
        }// end loop gateways
    });
});









