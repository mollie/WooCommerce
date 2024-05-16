const { expect, webkit, devices} = require('@playwright/test');
const { test } = require('../../Shared/base-test');
const {addProductToCart, emptyCart} = require("../../Shared/wooUtils");
const {allMethods, normalizedName} = require("../../Shared/gateways");

const orderGateways = Object.keys(allMethods);

const paymentGateways = Object.entries(allMethods).reduce((acc, [key, method]) => {
    if (!method.orderMandatory) {
        acc[key] = method;
    }
    return acc;
}, {});
test.describe('_Mollie Settings tab - Advanced', () => {
    test.beforeAll(async ({page, baseURL, products}) => {
        await addProductToCart(baseURL, products.simple.id, 10);
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select[name="mollie-payments-for-woocommerce_api_switch"]', 'order');
        await page.click('text=Save changes');
    });

    test('[C420152] Validate that Mollie Advanced section is displayed per UI design', async ({page}) => {
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await expect(await page.isVisible('text=Mollie Settings')).toBeTruthy();
        await expect(await page.isVisible('text=Advanced |')).toBeTruthy();
    });

    test('[C420154] Validate correct gateways shown with Order API on Classic checkout', async ({page}) => {
        await page.goto('/checkout');
        //select germany country as is the one that has all gateways
        await page.selectOption('select[name="billing_country"]', 'DE');
        //wait one second
        await page.waitForTimeout(1000);
        for (const gateway of orderGateways) {
            if (gateway === 'in3' || gateway === 'applepay') {
                continue;
            }
            let gatewayName = normalizedName(allMethods[gateway].defaultTitle)
            await expect(await page.isVisible(`text=${gatewayName}`)).toBeTruthy();
        }
        //select netherlands country as is the one that has in3
        await page.selectOption('select[name="billing_country"]', 'NL');
        await page.waitForTimeout(1000);
        let gatewayName = normalizedName(allMethods['in3'].defaultTitle)
        await expect(await page.isVisible(`text=${gatewayName}`)).toBeTruthy();
    });



    test.skip('[C420155] Validate correct gateways shown with Order API on Block checkout', async ({page}) => {
    });

    test.skip('[C420156] Validate correct gateways shown with Order API on order pay page', async ({page}) => {

    });

//beforeAll
    test.afterAll(async ({page, baseURL, products}) => {
        await emptyCart(baseURL);
    });

    test.skip('[C420157] Validate correct gateways shown with Payment API on Classic checkout', async ({page}) => {

    });

    test.skip('[C420158] Validate correct gateways shown with Payment  API on Block checkout', async ({page}) => {

    });

    test.skip('[C420159] Validate correct gateways shown with Payment API on order pay page', async ({page}) => {

    });

    test.skip('[C420160] Validate change of the API Payment description', async ({page}) => {
        // Your code here...
    });

    /*test.afterAll(async ({page, baseURL, products}) => {
        await emptyCart(baseURL);
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select[name="mollie-payments-for-woocommerce_api_switch"]', 'order');
        await page.click('text=Save changes');
    });*/

    test.skip('[C5813] Validate that merchant can clear Mollie data from database using clear now function', async ({page}) => {
        // Your code here...
    });

    test.skip('[C3332] Validate that the ecommerce admin can activate the use of Single-Click purchase', async ({page}) => {
        // Your code here...
    });

    test.skip('[C420153] Validate change of the payment screen language', async ({page}) => {
        // Your code here...
    });

    test.skip('[C420164] Validate that merchant can clear Mollie data from database on plugin uninstall', async ({page}) => {
        // Your code here...
    });
    test.skip('[C3347] Validate that the ecommerce admin can change the Description sent to Mollie regarding the order generated', async ({page}) => {
        // this is the same as C420160
    });

});
