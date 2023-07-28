const {test} = require('../../Shared/base-test');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {normalizedName} = require("../../Shared/gateways");
const {resetSettings, insertAPIKeys, setOrderAPI, beforePlacingOrder} = require("../../Shared/mollieUtils");
const {addProductToCart, emptyCart} = require("../../Shared/wooUtils");
const {expect} = require("@playwright/test");

test.describe('_Mollie Settings tab - Payment method settings', () => {
    // Set up parameters or perform actions before all tests
    test.beforeAll(async ({browser, products}) => {
        const page = await browser.newPage();
        await addProductToCart(page, products.simple.sku);
    });
    test.afterAll(async ({browser, products, gateways}) => {
        const page = await browser.newPage();
        await emptyCart(page);
    });

    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.bancontact;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        context.title = normalizedName(context.method.defaultTitle);
        await page.goto(context.tabUrl);
    });

test('[C3325] Validate that the ecommerce admin can change the payment name', async ({page, context}) => {
        await page.locator(`input[name="mollie_wc_gateway_${context.method.id}_title"]`).fill(`${context.title} edited`);
        await page.click('text=Save changes');
        await page.goto('/checkout');
        await expect(await page.isVisible(`text=${title} edited`)).toBeTruthy();
    });

test('[C3326] Validate that the ecommerce admin can change the payment logo', async ({page, context}) => {
        await page.getByLabel('Enable custom logo').check();
        await page.click('text=Save changes');
        await page.getByLabel('Upload custom logo').setInputFiles('tests/e2e/Shared/test-logo.png');
        await page.click('text=Save changes');
        await page.goto('/checkout');
        const url = await page.$eval(`text=${context.title} edited >> img`, img => img.src);
        await expect(url).toContain(`test-logo.png`)
    });

test('[C3327] Validate that the ecommerce admin can change the payment description', async ({page}) => {
        await page.locator(`textarea[name="mollie_wc_gateway_${context.method.id}_description"]`).fill(`${context.title} description edited`);
        await page.click('text=Save changes');
        await page.goto('/checkout');
        await expect(await page.isVisible(`text=${context.title} description edited`)).toBeTruthy();
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
        await page.goto('/checkout');
        await expect(await page.getByText(`${context.title} edited`).count()).toEqual(0);

    });

test.skip('[C420330] Validate that order expiry time can be activated and changed', async ({page}) => {
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
});
