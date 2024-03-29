const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');
const {normalizedName} = require("../../Shared/gateways");
const {noticeLines, checkExpiredAtMollie, settingsNames, classicCheckoutTransaction} = require("../../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage, wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {sharedUrl} = require("../../Shared/sharedUrl");
const {disableCheckboxSetting} = require("../../Shared/wpUtils");
const {emptyCart} = require("../../Shared/wooUtils");

test.describe('_Transaction scenarios_Payment statuses - Block Checkout - Credit card', () => {
    const productQuantity = 1;
    test.beforeEach(async ({ page , context, gateways}) => {
        context.method = gateways.creditcard;
        context.methodName = normalizedName(context.method.defaultTitle);
        await emptyCart(page);
        await page.goto('/shop/');
    });
    const testData = [
        {
            testId: "C420268",
            mollieStatus: "Paid",
            wooStatus: "Processing",
            notice: context => noticeLines.paid(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C420269",
            mollieStatus: "Open",
            wooStatus: "Pending payment",
            notice: context => noticeLines.open(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C420270",
            mollieStatus: "Failed",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C420272",
            mollieStatus: "Canceled",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C420271",
            mollieStatus: "Expired",
            wooStatus: "Pending payment",
            notice: context => noticeLines.expired(context.method.id),
            action: async (page) => {
                await checkExpiredAtMollie(page);
            }
        },
    ];


    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action }) => {
        test.skip(`[${testId}] Validate the submission of an order with Credit Card (Mollie Payment Screen) as payment method and payment mark as "${mollieStatus}"`, async ({ page, products, context }) => {
            //mollie components disabled
            const settingsTab = sharedUrl.gatewaySettingsRoot + context.method.id;
            const settingsName = settingsNames.components(context.method.id);
            await disableCheckboxSetting(page, settingsName, settingsTab);
            const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus, sharedUrl.blocksCheckout);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });

//TestId-C420273
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Paid"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420274
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Open"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420275
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Failed"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420276
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Expired"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420277
test.skip('Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Canceled"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420278
test.skip('Validate the submission of an order with Credit Card (no 3D secure) as payment method using Mollie Components  on block chcekout', async ({ page}) => {
  // Your code here...
});


});
