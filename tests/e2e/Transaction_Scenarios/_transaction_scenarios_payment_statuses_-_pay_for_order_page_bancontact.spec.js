const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const { execSync } = require('child_process');
const {selectPaymentMethodInCheckout, placeOrderPayPage, captureTotalAmountPayPage, createManualOrder} = require("../Shared/wooUtils");
const {normalizedName} = require("../Shared/gateways");
const {noticeLines, checkExpiredAtMollie, classicCheckoutTransaction, processMollieCheckout} = require("../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage, wooOrderDetailsPage} = require("../Shared/testMollieInWooPage");
const {sharedUrl} = require("../Shared/sharedUrl");

test.describe('_Transaction scenarios_Payment statuses - Pay for order page - Bancontact', () => {
  test.beforeEach(async ({ page , context, gateways}) => {
      context.method = gateways.bancontact;
      context.methodName = normalizedName(context.method.defaultTitle);
      await createManualOrder(page, 'Beanie')
  });
    const testData = [
        {
            testId: "C420345",
            mollieStatus: "Paid",
            wooStatus: "Processing",
            notice: context => noticeLines.paid(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C420346",
            mollieStatus: "Open",
            wooStatus: "Pending payment",
            notice: context => noticeLines.open(context.methodName),
            action: async (page, result, context) => {
                await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
            }
        },
        {
            testId: "C420347",
            mollieStatus: "Failed",
            wooStatus: "Failed",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C420348",
            mollieStatus: "Canceled",
            wooStatus: "Pending payment",
            notice: context => noticeLines.failed(context.method.id),
            action: async (page) => {
                await wooOrderRetryPage(page);
            }
        },
        {
            testId: "C420349",
            mollieStatus: "Expired",
            wooStatus: "Pending payment",
            notice: context => noticeLines.expired(context.method.id),
            action: async (page) => {
                await checkExpiredAtMollie(page);
            }
        },
    ];

    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action }) => {
        test(`[TestId-${testId}] Validate the submission of an order with Bancontact as payment method and payment mark as "${mollieStatus} on pay for order page"`, async ({ page, products, context }) => {
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
