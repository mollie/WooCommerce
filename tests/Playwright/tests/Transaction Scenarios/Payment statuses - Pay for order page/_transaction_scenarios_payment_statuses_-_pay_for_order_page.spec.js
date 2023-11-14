const { test } = require('../../Shared/base-test');
const {normalizedName} = require("../../Shared/gateways");
const {
    createManualOrder,
    selectPaymentMethodInCheckout,
    captureTotalAmountPayPage,
    placeOrderPayPage
} = require("../../Shared/wooUtils");
const {processMollieCheckout, resetSettings, insertAPIKeys, setOrderAPI} = require("../../Shared/mollieUtils");
const {wooOrderDetailsPage} = require("../../Shared/testMollieInWooPage");
const {testData} = require("./testData");

testData.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses - Pay for order page - ${methodId}`, () => {
        test.beforeEach(async ({page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
        });
        test(`[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus} on pay for order page"`, async ({ page, products, context
        }) => {
            let country = 'DE'
            let postcode = '10115'
            let quantity = 1
            if (methodId === 'in3'){
                country = 'NL'
                postcode = '1012 JS'
                quantity = 10
            }
            if (methodId === 'klarnasliceit'){
                quantity = 10
            }
            const orderData = await createManualOrder(page, 14, quantity, country, postcode)
            //console.log(orderData)
            await page.goto(orderData.url);

            await selectPaymentMethodInCheckout(page, context.methodName);
            const totalAmount = await captureTotalAmountPayPage(page);
            await placeOrderPayPage(page);
            await page.waitForTimeout(5000)
            const mollieOrder = await processMollieCheckout(page, mollieStatus);
            const result = {mollieOrder: mollieOrder, totalAmount: totalAmount};
            await page.goto(`/checkout/order-received/${orderData.orderId}?key=${orderData.orderKey}&order_id=${orderData.orderId}&filter_flag=onMollieReturn`)
            await action(page, result, context);
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context));
        });
    });
});
