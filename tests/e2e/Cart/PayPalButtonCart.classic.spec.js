// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {resetSettings, insertAPIKeys} = require('../Shared/mollieUtils');
const {addProductToCart} = require('../Shared/wooUtils');
const {sharedUrl: {paypalSettings}} = require('../Shared/sharedUrl');

test.describe('PayPal Transaction in classic cart', () => {
    test.beforeAll(async ({browser , config}) => {
        const page = await browser.newPage({ baseURL: config.projects[0].use.baseURL });
        await resetSettings(page);
        await insertAPIKeys(page);
    });
    test('Not be seen if not enabled', async ({page, products}) => {
        await page.goto(paypalSettings);
        await page.locator('input[name="mollie_wc_gateway_paypal_mollie_paypal_button_enabled_cart"]').uncheck();
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        await addProductToCart(page, products.virtual.name);
        //go to cart and not see
        await page.goto('/cart/');
        await expect(page.locator('#mollie-PayPal-button')).not.toBeVisible();
        //remove from cart
        await page.locator('tr.woocommerce-cart-form__cart-item.cart_item > td.product-remove > a').click();
    });
});
