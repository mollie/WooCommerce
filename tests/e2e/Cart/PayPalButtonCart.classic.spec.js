// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {resetSettings, insertAPIKeys} = require('../Shared/mollieUtils');
const {addProductToCart} = require('../Shared/wooUtils');

test.describe('PayPal Transaction in classic cart', () => {
    test.beforeAll(async ({browser }) => {
        const page = await browser.newPage();
        await resetSettings(page);
        await insertAPIKeys(page);
    });
    test('Not be seen if not enabled', async ({page, products}) => {
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=mollie_wc_gateway_paypal');
        await page.locator('input[name="mollie_wc_gateway_paypal_mollie_paypal_button_enabled_cart"]').uncheck();
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        await addProductToCart(page, products.virtual.name);
        //go to cart and not see
        await page.goto(process.env.E2E_URL_TESTSITE + '/cart/');
        await expect(page.locator('#mollie-PayPal-button')).not.toBeVisible();
        //remove from cart
        await page.locator('tr.woocommerce-cart-form__cart-item.cart_item > td.product-remove > a').click();
    });
});
