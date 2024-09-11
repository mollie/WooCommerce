const { test } = require('../../../fixtures/base-test');
const {noticeLines, checkExpiredAtMollie, settingsNames, checkoutTransaction} = require("../../../utils/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage, wooOrderDetailsPage} = require("../../../utils/testMollieInWooPage");
const {updateMethodSetting, addProductToCart} = require("../../../utils/wooUtils");

test.describe('_Transaction scenarios_Payment statuses Checkout - Credit card', () => {
    const productQuantity = 1;
    test.beforeAll(async ({ gateways, baseURL }) => {
        const payload = {
            "settings": {
                [settingsNames.components]: 'no',
            }
        }
        await updateMethodSetting(gateways.creditcard.id, payload, baseURL);
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
//TODO I have a failed to fetch error now in Mollie's page
    testData.forEach(({ testId, mollieStatus, wooStatus, notice, action}) => {
        test.skip(`[${testId}] Validate the submission of an order with Credit Card (Mollie Payment Screen) as payment method and payment mark as "${mollieStatus}"`, async ({ page, products, context, baseURL, gateways }) => {
            //mollie components disabled
            await addProductToCart(baseURL, products.simple.id, productQuantity);
            await page.goto('/checkout/');
            const result = await checkoutTransaction(page, products.simple, gateways.creditcard, productQuantity, mollieStatus);
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, gateways.creditcard, wooStatus, notice(context), baseURL);
        });
    });


//TestId-C3376
    test.skip('[C3376] Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Paid"', async ({ page }) => {
        // Your code here...
    });

//TestId-C3377
    test.skip('[C3377] Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Open"', async ({ page }) => {
        // Your code here...
    });

//TestId-C3378
    test.skip('[C3378] Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Failed"', async ({ page }) => {
        // Your code here...
    });

//TestId-C3379
    test.skip('[C3379] Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Expired"', async ({ page }) => {
        // Your code here...
    });

//TestId-C3380
    test.skip('[C3380] Validate the submission of an order with Credit Card as payment method using Mollie Components and payment mark as "Canceled"', async ({ page }) => {
        // Your code here...
    });

//TestId-C3381
    test.skip('[C3381] Validate the submission of an order with Credit Card (no 3D secure) as payment method using Mollie Components', async ({ page }) => {
        // Your code here...
    });



});
