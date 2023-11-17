const { test } = require('../../Shared/base-test');
const {normalizedName} = require("../../Shared/gateways");
const {checkoutTransaction, resetSettings, insertAPIKeys, setOrderAPI} = require("../../Shared/mollieUtils");
const {wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {sharedUrl} = require("../../Shared/sharedUrl");
const {emptyCart, addProductToCart} = require("../../Shared/wooUtils");
const {testData} = require("./testData");
const {expect} = require("@playwright/test");

testData.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses - Block Checkout - ${methodId}`, () => {
        const productQuantity = 1;
        test.beforeEach(async ({page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
        });
        test(
            `[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus} on block checkout"`,
            async ({page, products, context, baseURL}
            ) => {
            await addProductToCart(baseURL, products.simple.id, productQuantity);
            const result = await checkoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus, 'checkout-block');
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });
});
