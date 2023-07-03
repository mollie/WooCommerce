const { test } = require('../../Shared/base-test');
const {
    setOrderAPI,
    insertAPIKeys,
    resetSettings,
    checkoutTransaction
} = require('../../Shared/mollieUtils');
const {wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {normalizedName} = require("../../Shared/gateways");
const {emptyCart} = require("../../Shared/wooUtils");
const {testData} = require("./testData");
// Set up parameters or perform actions before all tests
test.beforeAll(async ({browser}) => {
    // Create a new page instance
    const page = await browser.newPage();
    const context = await browser.newContext();
    context.page = page;
    // Reset to the default state
    await resetSettings(context.page);
    await insertAPIKeys(context.page);
    // Orders API
    await setOrderAPI(context.page);
});

testData.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses Checkout - ${methodId}`, () => {
        const productQuantity = 1;
        test.beforeEach(async ({page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
            await emptyCart(page);
            await page.goto('/shop/');
        });
        test(`[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus}"`, async ({
                                                                                                                                                   page,
                                                                                                                                                   products,
                                                                                                                                                   context
                                                                                                                                               }) => {
            const result = await checkoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });
});
