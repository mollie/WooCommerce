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

test.describe('Should show payment settings on classic checkout', () => {
    test.beforeAll(async ({browser }) => {
        const page = await browser.newPage();
        //login as Admin
        await loginAdmin(page);
        await resetSettings(page);
        await insertAPIKeys(page);
        // Go to shop
        await page.goto(process.env.E2E_URL_TESTSITE + '/shop/');
        // Add product to cart
        const productCartButton = PRODUCTS.simple.name;
        await page.locator('[data-product_sku="' + productCartButton + '"]').click();
    });
    for ( const key in GATEWAYS){
    test(`Should show ${key} default settings`, async ({ page }) => {
            // Go to checkout
            await page.goto(process.env.E2E_URL_TESTSITE + '/checkout');
            let testedGateway = GATEWAYS[key]
            //check default title
            await page.locator('#payment_method_mollie_wc_gateway_' + testedGateway.id)
            await expect(page.locator('#payment')).toContainText(testedGateway.defaultTitle);
            //check default icon
            const url = await page.$eval(`text=${testedGateway.defaultTitle} >> img`, img => img.src);
            await expect(url).toEqual(`${process.env.E2E_URL_TESTSITE}/wp-content/plugins/${process.env.E2E_TESTPACKAGE}//public/images/${testedGateway.id}.svg`)
            //check default description
            await expect(page.locator('#payment')).toContainText(testedGateway.defaultDescription);
            //check issuers dropdown show
            if(testedGateway.paymentFields){
                let issuers = page.locator(`#payment > ul > li.wc_payment_method.payment_method_mollie_wc_gateway_${testedGateway.id} > div`)
                await expect(issuers).toContainText(testedGateway.defaultDescription)
            }
            //no fee added
            await expect(page.locator('#order_review')).not.toContainText('Fee')
    });
    }// end loop gateways
    for ( const key in GATEWAYS){
        test(`Should show ${key} custom settings`, async ({ page }) => {
                let testedGateway = GATEWAYS[key]
                await loginAdmin(page);
                //set custom settings
                await page.goto(`${process.env.E2E_URL_TESTSITE}/wp-admin/admin.php?page=wc-settings&tab=checkout&section=mollie_wc_gateway_${testedGateway.id}`)
                await page.locator(`input[name="mollie_wc_gateway_${testedGateway.id}_title"]`).fill(`${testedGateway.defaultTitle} edited`);
                await page.locator(`textarea[name="mollie_wc_gateway_${testedGateway.id}_description"]`).fill(`${testedGateway.defaultTitle} description edited`);
                await page.locator(`input[name="mollie_wc_gateway_${testedGateway.id}_display_logo"]`).uncheck();
                //await page.locator(`#mainform > table:nth-child(9) > tbody > tr > td > span.select2.select2-container.select2-container--default > span.selection > span > ul > li > input`).click();
                await page.locator('[placeholder="Choose countries…"]').click();
                await page.locator('[placeholder="Choose countries…"]').fill('spa');
                await page.locator('li[role="option"]:has-text("Spain")').click();
                await page.locator(`select[name="mollie_wc_gateway_${testedGateway.id}_payment_surcharge"]`).selectOption('fixed_fee');
                await page.locator(`input[name="mollie_wc_gateway_${testedGateway.id}_fixed_fee"]`).fill('10');
                if(testedGateway.paymentFields){
                    await page.locator(`input[name="mollie_wc_gateway_${testedGateway.id}_issuers_dropdown_shown"]`).uncheck();
                }
                await Promise.all([
                    page.waitForNavigation(),
                    page.locator('text=Save changes').click()
                  ]);
                // Go to checkout
                await page.goto(process.env.E2E_URL_TESTSITE + '/checkout');

                //check custom title
                await page.locator(`select[name="billing_country"]`).selectOption('ES');
                await page.locator(`#payment > ul > li.wc_payment_method.payment_method_mollie_wc_gateway_${testedGateway.id} > label`).click()
                await expect(page.locator(`#payment > ul > li.wc_payment_method.payment_method_mollie_wc_gateway_${testedGateway.id} > label`)).toContainText(`${testedGateway.defaultTitle} edited`);
                //check not display logo
                await expect(page.locator(`text=${testedGateway.defaultTitle} >> img`)).toBeFalsy
                //check custom description
                await expect(page.locator('#payment')).toContainText(`${testedGateway.defaultTitle} description edited`);
                //check issuers dropdown not show
                if(testedGateway.paymentFields){
                    let issuers = page.locator(`#payment > ul > li.wc_payment_method.payment_method_mollie_wc_gateway_${testedGateway.id} > div`)
                    await expect(issuers).toBeEmpty
                }
                //check fee added
                await expect(page.locator('#order_review')).toContainText('Fee')
                //check not sell to countries
                await page.locator(`select[name="billing_country"]`).selectOption('DE');
                await expect(page.locator(`#payment > ul > li.wc_payment_method.payment_method_mollie_wc_gateway_${testedGateway.id} > label`)).not.toBeVisible();
                await page.locator(`select[name="billing_country"]`).selectOption('ES');

        });
        }// end loop gateways
});









