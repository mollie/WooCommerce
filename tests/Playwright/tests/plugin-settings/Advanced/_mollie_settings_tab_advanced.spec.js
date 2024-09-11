const { expect, webkit, devices} = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');
const {addProductToCart, emptyCart, changeWooCurrency} = require("../../../utils/wooUtils");
const {allMethods, normalizedName} = require("../../../utils/gateways");

const orderGateways = Object.values(allMethods);

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

    test('[C420154] Validate correct gateways shown with Order API on Classic checkout', async ({page, baseURL}) => {
        await page.goto('/checkout-classic');
        let setCurrency = null;
        for (const gateway of orderGateways) {
            // woocommerce api change currency based on gateway
            if (setCurrency !== gateway.currency) {
                setCurrency = gateway.currency;
                await changeWooCurrency(baseURL, gateway.currency)
                await page.reload();
            }
            await page.selectOption('select[name="billing_country"]', gateway.country);
            await page.waitForTimeout(600);

            if (gateway.id === 'applepay') {
                continue;
            }
            let gatewayName = normalizedName(gateway.defaultTitle)
            await expect(await page.isVisible(`text=${gatewayName}`)).toBeTruthy();
        }
    });



    /*test.skip('[C420155] Validate correct gateways shown with Order API on Block checkout', async ({page}) => {
    });

    test.skip('[C420156] Validate correct gateways shown with Order API on order pay page', async ({page}) => {

    });*/

//beforeAll
    test.afterAll(async ({page, baseURL, products}) => {
        await emptyCart(baseURL);
        await changeWooCurrency(baseURL, 'EUR')
    });

    /*test.skip('[C420157] Validate correct gateways shown with Payment API on Classic checkout', async ({page}) => {

    });

    test.skip('[C420158] Validate correct gateways shown with Payment  API on Block checkout', async ({page}) => {

    });

    test.skip('[C420159] Validate correct gateways shown with Payment API on order pay page', async ({page}) => {

    });

    test.skip('[C420160] Validate change of the API Payment description', async ({page}) => {
        // Your code here...
    });*/

    /*test.afterAll(async ({page, baseURL, products}) => {
        await emptyCart(baseURL);
        await page.goto('wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select[name="mollie-payments-for-woocommerce_api_switch"]', 'order');
        await page.click('text=Save changes');
    });*/

    /*test.skip('[C5813] Validate that merchant can clear Mollie data from database using clear now function', async ({page}) => {
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
*/
});
