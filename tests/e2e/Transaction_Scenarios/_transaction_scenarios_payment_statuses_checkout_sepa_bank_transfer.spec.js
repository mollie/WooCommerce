const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {normalizedName} = require("../Shared/gateways");
const {noticeLines, checkExpiredAtMollie, classicCheckoutTransaction} = require("../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage, wooOrderDetailsPage} = require("../Shared/testMollieInWooPage");

test.describe('_Transaction scenarios_Payment statuses Checkout - SEPA Bank Transfer', () => {
    const productQuantity = 1;
    test.beforeEach(async ({ page , context, gateways}) => {
        context.method = gateways.banktransfer;
        context.methodName = normalizedName(context.method.defaultTitle);
        await page.goto('/shop/');
    });
    const testData = [
        {
            testId: "C3433",
            mollieStatus: "Paid",
            wooStatus: "Processing",
            notice: context => noticeLines.paid(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C3432",
            mollieStatus: "Open",
            wooStatus: "Pending payment",
            notice: context => noticeLines.open(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C3434",
            mollieStatus: "Expired",
            wooStatus: "Pending payment",
            notice: context => noticeLines.expired(context.method.id),
            action: async (page) => {
                await checkExpiredAtMollie(page);
            }
        },
    ];


    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action }) => {
        test(`[TestId-${testId}] Validate the submission of an order with SEPA Bank Transfer as payment method and payment mark as "${mollieStatus}"`, async ({ page, products, context }) => {
            const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });
});
