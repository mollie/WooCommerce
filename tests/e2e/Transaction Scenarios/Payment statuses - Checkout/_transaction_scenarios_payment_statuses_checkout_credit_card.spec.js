const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');
const {normalizedName} = require("../../Shared/gateways");
const {noticeLines, checkExpiredAtMollie, classicCheckoutTransaction, settingsNames} = require("../../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage, wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {enableCheckboxSetting, disableCheckboxSetting} = require("../../Shared/wpUtils");
const {sharedUrl} = require("../../Shared/sharedUrl");
const {emptyCart} = require("../../Shared/wooUtils");

test.describe('_Transaction scenarios_Payment statuses Checkout - Credit card', () => {
    const productQuantity = 1;
    test.beforeEach(async ({ page , context, gateways}) => {
        context.method = gateways.creditcard;
        context.methodName = normalizedName(context.method.defaultTitle);
        await emptyCart(page);
        await page.goto('/shop/');
    });
    const testData = [
        {
            testId: "C3371",
            mollieStatus: "Paid",
            wooStatus: "Processing",
            notice: context => noticeLines.paid(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C3372",
            mollieStatus: "Open",
            wooStatus: "Pending payment",
            notice: context => noticeLines.open(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C3373",
            mollieStatus: "Failed",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C3375",
            mollieStatus: "Canceled",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C3374",
            mollieStatus: "Expired",
            wooStatus: "Pending payment",
            notice: context => noticeLines.expired(context.method.id),
            action: async (page) => {
                await checkExpiredAtMollie(page);
            }
        },
    ];


    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action }) => {
        test(`[${testId}] Validate the submission of an order with Credit Card (Mollie Payment Screen) as payment method and payment mark as "${mollieStatus}"`, async ({ page, products, context }) => {
            //mollie components disabled
            const settingsTab = sharedUrl + context.method.id;
            const settingsName = settingsNames.components(context.method.id);
            await disableCheckboxSetting(page, settingsName, settingsTab);
            const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });


//TestId-C3376
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Paid"', async ({ page}) => {
  // Your code here...
});


//TestId-C3377
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Open"', async ({ page}) => {
  // Your code here...
});


//TestId-C3378
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Failed"', async ({ page}) => {
  // Your code here...
});


//TestId-C3379
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Expired"', async ({ page}) => {
  // Your code here...
});


//TestId-C3380
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Canceled"', async ({ page}) => {
  // Your code here...
});


//TestId-C3381
test.skip('Validate the submission of an order with Credit Card (no 3D secure) as payment method using Mollie Components', async ({ page}) => {
  // Your code here...
});


});
