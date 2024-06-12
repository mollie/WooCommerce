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
        test.beforeEach(async ({page,products, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
        });
        test(`[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus} on pay for order page"`, async ({ page, products, context
        }) => {
            test.slow()
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
            const orderData = await createManualOrder(page, products.simple.id, quantity, country, postcode)
            await page.goto(orderData.url);

            await selectPaymentMethodInCheckout(page, context.methodName);
            if (methodId === 'billie') {
                await page.locator('input[id="billing_company"]').first().fill('My company name');
            }
            const canFillBirthDate = await page.locator('input[name="billing_birthdate"]').first().isVisible();
            if (canFillBirthDate) {
                await page.locator('input[name="billing_birthdate"]').first().fill('1990-01-01');
            }
            const canFillPhone = await page.locator('input[name="billing_phone_in3"]').first().isVisible();
            if (canFillPhone) {
                await page.locator('input[name="billing_phone_in3"]').first().fill('+341234566788');
            }
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
