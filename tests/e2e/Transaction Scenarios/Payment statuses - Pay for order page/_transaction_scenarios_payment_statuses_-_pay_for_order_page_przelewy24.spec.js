const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');
const {normalizedName} = require("../../Shared/gateways");
const {
    createManualOrder,
    selectPaymentMethodInCheckout,
    captureTotalAmountPayPage,
    placeOrderPayPage
} = require("../../Shared/wooUtils");
const {noticeLines, checkExpiredAtMollie, processMollieCheckout} = require("../../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage, wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");

test.describe('_Transaction scenarios_Payment statuses - Pay for order page - Przelewy24', () => {
    test.beforeEach(async ({ page , context, gateways}) => {
        context.method = gateways.przelewy24;
        context.methodName = normalizedName(context.method.defaultTitle);
        await createManualOrder(page, 'Beanie')
    });
    const testData = [
        {
            testId: "C420350",
            mollieStatus: "Paid",
            wooStatus: "Processing",
            notice: context => noticeLines.paid(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C420351",
            mollieStatus: "Failed",
            wooStatus: "Failed",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C420352",
            mollieStatus: "Canceled",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C420353",
            mollieStatus: "Expired",
            wooStatus: "Pending payment",
            notice: context => noticeLines.expired(context.method.id),
            action: async (page) => {
                await checkExpiredAtMollie(page);
            }
        },
    ];

    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action }) => {
        test(`[${testId}] Validate the submission of an order with P24 as payment method and payment mark as "${mollieStatus} on pay for order page"`, async ({ page, products, context }) => {
            await selectPaymentMethodInCheckout(page, context.methodName);
            const totalAmount = await captureTotalAmountPayPage(page);
            await placeOrderPayPage(page);
            const mollieOrder = await processMollieCheckout(page, mollieStatus);
            const result = {mollieOrder: mollieOrder, totalAmount: totalAmount};
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });
});
