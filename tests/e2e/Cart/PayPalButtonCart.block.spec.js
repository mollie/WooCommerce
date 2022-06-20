// @ts-check
const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {setOrderAPI, markStatusInMollie} = require('../Shared/mollieUtils');
const {addProductToCart} = require('../Shared/wooUtils');
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid} = require('../Shared/testMollieInWooPage');

test.describe('PayPal Transaction in classic cart', () => {
    test('Not be seen if not enabled', async ({page, products}) => {
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=mollie_wc_gateway_paypal');
        await page.locator('input[name="mollie_wc_gateway_paypal_mollie_paypal_button_enabled_cart"]').uncheck();
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        await addProductToCart(page, products.virtual.name);
        //go to cart and not see
        await page.goto(process.env.E2E_URL_TESTSITE + '/cart-block/');
        await expect(page.locator('#mollie-PayPal-button')).not.toBeVisible();
        //remove from cart
        await page.locator('text=Remove item').click();
    });
    test('Not be seen if not virtual', async ({page, products}) => {
        // set PayPal visible in cart
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=mollie_wc_gateway_paypal');
        await page.locator('input[name="mollie_wc_gateway_paypal_mollie_paypal_button_enabled_cart"]').check();
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        await addProductToCart(page, products.simple.name);
        //go to cart and not see
        await page.goto(process.env.E2E_URL_TESTSITE + '/cart-block/');
        await expect(page.locator('#mollie-PayPal-button')).not.toBeVisible();
        //remove from cart
        await page.locator('text=Remove item').click();
    });
    test('Transaction with Order API - virtual product', async ({page, gateways, products}) => {
        let testedGateway = gateways
        await setOrderAPI(page);
        await addProductToCart(page, products.virtual.name);
        //go to cart and click
        await page.goto(process.env.E2E_URL_TESTSITE + '/cart-block/');
        await expect(page.locator('#mollie-PayPal-button')).toBeVisible();
        //Capture WooCommerce total amount
        const totalAmount = await page.innerText('div.wp-block-woocommerce-cart-order-summary-block > div:nth-child(4) > div > span.wc-block-formatted-money-amount.wc-block-components-formatted-money-amount.wc-block-components-totals-item__value');
        await Promise.all([
            page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=paypal&token=3.q6wq1i' }*/),
            page.locator('input[alt="PayPal Button"]').click()
        ]);
        // IN MOLLIE
        // Capture order number in Mollie and mark as paid
        const mollieOrder = await markStatusInMollie(page, "Paid");

        // WOOCOMMERCE ORDER PAID PAGE
        await wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway);

        // WOOCOMMERCE ORDER PAGE
        await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
    });
});
