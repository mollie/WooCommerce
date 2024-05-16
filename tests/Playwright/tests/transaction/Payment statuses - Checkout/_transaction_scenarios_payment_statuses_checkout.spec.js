const { test } = require('../../Shared/base-test');
const { request } = require('@playwright/test');
const {
    setOrderAPI,
    insertAPIKeys,
    resetSettings,
    checkoutTransaction
} = require('../../Shared/mollieUtils');
const {wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {normalizedName} = require("../../Shared/gateways");
const {emptyCart, addProductToCart} = require("../../Shared/wooUtils");
const {testData} = require("./testData");
testData.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses Checkout - ${methodId}`, () => {
        let productQuantity = 1;
        test.beforeEach(async ({browser, page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);

        });
        test(
            `[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus}"`,
            async ({page,products,context,baseURL, gateways}
            ) => {
                test.slow();
                await emptyCart(baseURL);
                if(methodId === 'in3' || methodId === 'klarnasliceit'){
                    productQuantity = 10;
                }
            await addProductToCart(baseURL, products.simple.id, productQuantity);
            await page.goto('/checkout/');
            const result = await checkoutTransaction(page, products.simple, gateways[methodId], productQuantity, mollieStatus, 'checkout');
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, gateways[methodId], wooStatus, notice(context));
        });
        test.afterEach(async ({page, context}) => {
            await context.close()
        });

    });
});
test.skip('[C420148] Validate that order status after cancelled payment is set to pending status', async ({ page}) => {
    await page.selectOption('select[name="mollie-payments-for-woocommerce_order_status_cancelled_payments"]', 'pending');
    await page.click('text=Save changes');
});

test.skip('[C420149] Validate that order status after cancelled payment is set to cancelled status', async ({ page}) => {
    await page.selectOption('select[name="mollie-payments-for-woocommerce_order_status_cancelled_payments"]', 'cancelled');
    await page.click('text=Save changes');
});
test.skip('[C3367] Validate the creation of an order using the Orders API', async ({page}) => {
    // This is duplicated all transactions tests are done using orders api
});
test.skip('[C3368] Validate the creation of an order using the Payments API', async ({page}) => {
    // This is duplicated we have already tests for this
});
