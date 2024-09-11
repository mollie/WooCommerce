const {test} = require('../../../fixtures/base-test');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../../utils/sharedUrl');
const {normalizedName} = require("../../../utils/gateways");
const {resetSettings, insertAPIKeys, setOrderAPI, beforePlacingOrder} = require("../../../utils/mollieUtils");
const {addProductToCart, emptyCart} = require("../../../utils/wooUtils");
const {expect} = require("@playwright/test");
const {join} = require("node:path");

test.describe('_Mollie Settings tab - Payment method settings', () => {
    // Set up parameters or perform actions before all tests
    test.beforeAll(async ({baseURL, products}) => {
        await addProductToCart(baseURL, products.simple.id, 5);
    });
    test.afterAll(async ({baseURL, page}) => {
        await emptyCart(baseURL);
        await resetSettings(page);
        await insertAPIKeys(page);
    });

    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.eps;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        context.title = normalizedName(context.method.defaultTitle);
        await page.goto(context.tabUrl);
    });

test('[C3325] Validate that the ecommerce admin can change the payment name', async ({page, context}) => {
    console.log(context.method.id);
    await page.locator(`input[name="mollie_wc_gateway_${context.method.id}_title"]`).fill(`${context.method.id} edited`);
        await page.click('text=Save changes');
        await page.goto('/checkout-classic');
        await expect(await page.isVisible(`text=${context.method.id} edited`)).toBeTruthy();
    });

test('[C3326] Validate that the ecommerce admin can change the payment logo', async ({page, context}) => {
        await page.getByLabel('Enable custom logo').check();
        await page.click('text=Save changes');
    const fileChooserPromise = page.waitForEvent('filechooser');
    await page.getByText('Upload custom logo').first().click();
    const fileChooser = await fileChooserPromise;
    await fileChooser.setFiles(join(__dirname, 'test-logo.png'));
        await page.click('text=Save changes');
    await page.goto('/checkout-classic');
        const url = await page.$eval(`text=${context.title} edited >> img`, img => img.src);
        await expect(url).toContain(`test-logo.png`)
    });

test('[C3327] Validate that the ecommerce admin can change the payment description', async ({page, context}) => {
        await page.locator(`textarea[name="mollie_wc_gateway_${context.method.id}_description"]`).fill(`${context.method.id} description edited`);
        await page.click('text=Save changes');
        await expect(await page.isVisible(`text=${context.method.id} description edited`)).toBeTruthy();
        await page.goto('/checkout-classic');
        await page.click(`text=${context.method.id}`);
        await expect(await page.isVisible(`text=${context.method.id} description edited`)).toBeTruthy();
    });

test('[C420329] Validate selling only to specific countries', async ({page, context}) => {
        const previousValue = await page.getByRole('link', {name: 'Select none'}).isVisible();
        if (previousValue) {
            await page.click('text=Select none');
            await page.click('text=Save changes');
        }
        await page.locator('[placeholder="Choose countries…"]').click();
        await page.locator('[placeholder="Choose countries…"]').fill('spa');
        await page.locator('li[role="option"]:has-text("Spain")').click();
        await page.click('text=Save changes');
        await page.goto('/checkout-classic');
        await expect(await page.getByText(`${context.method.id} edited`).count()).toEqual(0);

    });

/*test.skip('[C420330] Validate that order expiry time can be activated and changed', async ({page}) => {
        await page.getByLabel('Activate expiry time setting').check();
        await page.getByLabel('Expiry time', {exact: true}).fill('1');
        await page.click('text=Save changes');
        await expect(await page.getByLabel('Activate expiry time setting').isChecked()).toBeTruthy();
        await expect(await page.getByLabel('Expiry time', {exact: true})).toHaveValue('1');
    });

test.skip('[C420331] Validate that initial order status can be set to "On Hold"', async ({page}) => {
        await expect(await page.getByRole('combobox', {name: 'Initial order status'})).toHaveValue('on-hold');
    });

test.skip('[C420332] Validate that initial order status can be set to "Pending payment"', async ({page}) => {
        await page.getByLabel('Initial order status').selectOption('pending');
        await page.click('text=Save changes');
        await expect(await page.getByRole('combobox', {name: 'Initial order status'})).toHaveValue('pending');
    });
    test.skip('[C93487] Validate expiry time for Bancontact', async ({ page}) => {
        // Your code here...
    });
    test.skip('[C3362] Validate that the iDEAL issuer list available in payment selection', async ({ page}) => {
        // Your code here...
    });


    test.skip('[C89358] Validate expiry time for IDEAL', async ({ page}) => {
        // Your code here...
    });
    test.skip('[C127228] Validate expiry time for SEPA Bank Transfer', async ({ page}) => {
        // Your code here...
    });*/
});
