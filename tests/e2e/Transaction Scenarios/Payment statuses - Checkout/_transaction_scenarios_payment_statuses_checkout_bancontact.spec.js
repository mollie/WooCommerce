const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');
const {
    setOrderAPI,
    insertAPIKeys,
    resetSettings,
    settingsNames,
    noticeLines,
    classicCheckoutTransaction, checkExpiredAtMollie
} = require('../../Shared/mollieUtils');
const {wooOrderPaidPage, wooOrderDetailsPage, wooOrderRetryPage} = require("../../Shared/testMollieInWooPage");
const {normalizedName} = require("../../Shared/gateways");
const {emptyCart} = require("../../Shared/wooUtils");

// Set up parameters or perform actions before all tests
test.beforeAll(async ({browser}) => {
    // Create a new page instance
    const page = await browser.newPage();
    // Reset to the default state
    await resetSettings(page);
    await insertAPIKeys(page);
    // Orders API
    await setOrderAPI(page);
});
test.describe('_Transaction scenarios_Payment statuses Checkout - Bancontact', () => {
    const productQuantity = 1;
  test.beforeEach(async ({ page , context, gateways}) => {
      context.method = gateways.bancontact;
      context.methodName = normalizedName(context.method.defaultTitle);
      await emptyCart(page);
      await page.goto('/shop/');
  });
    const testData = [
        {
            testId: "C3387",
            mollieStatus: "Paid",
            wooStatus: "Processing",
            notice: context => noticeLines.paid(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C3388",
            mollieStatus: "Open",
            wooStatus: "Pending payment",
            notice: context => noticeLines.open(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C3389",
            mollieStatus: "Failed",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C3390",
            mollieStatus: "Canceled",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C3391",
            mollieStatus: "Expired",
            wooStatus: "Pending payment",
            notice: context => noticeLines.expired(context.method.id),
            action: async (page) => {
                await checkExpiredAtMollie(page);
            }
        },
    ];


    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action }) => {
        test(`[${testId}] Validate the submission of an order with Bancontact as payment method and payment mark as "${mollieStatus}"`, async ({ page, products, context }) => {
            const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });

});
