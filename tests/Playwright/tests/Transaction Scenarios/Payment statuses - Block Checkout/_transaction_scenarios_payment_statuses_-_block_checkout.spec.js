const { test } = require('../../Shared/base-test');
const {normalizedName} = require("../../Shared/gateways");
const {checkoutTransaction, resetSettings, insertAPIKeys, setOrderAPI} = require("../../Shared/mollieUtils");
const {wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {sharedUrl} = require("../../Shared/sharedUrl");
const {emptyCart} = require("../../Shared/wooUtils");
const {testData} = require("./testData");
test.beforeAll(async ({browser}) => {
    // Create a new page instance
    const page = await browser.newPage();
    // Reset to the default state
    await resetSettings(page);
    await insertAPIKeys(page);
    // Orders API
    await setOrderAPI(page);
});

testData.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses - Block Checkout - ${methodId}`, () => {
        const productQuantity = 1;
        test.beforeEach(async ({page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
            await emptyCart(page);
            await page.goto('/shop/');
        });
        test(`[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus} on block checkout"`, async ({
                                                                                                                                                                     page,
                                                                                                                                                                     products,
                                                                                                                                                                     context
                                                                                                                                                                 }) => {
            const result = await checkoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus, sharedUrl.blocksCheckout);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });
});
