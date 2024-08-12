const { test } = require('../../../fixtures/base-test');
const {normalizedName} = require("../../../utils/gateways");
const {
    createManualOrder,
    selectPaymentMethodInCheckout,
    captureTotalAmountPayPage,
    placeOrderPayPage
} = require("../../../utils/wooUtils");
const {processMollieCheckout, resetSettings, insertAPIKeys, setOrderAPI} = require("../../../utils/mollieUtils");
const {wooOrderDetailsPage} = require("../../../utils/testMollieInWooPage");
const {payOrderPageTransaction} = require("../../../test-data/pay-order-page-transaction");

payOrderPageTransaction.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses - Pay for order page - ${methodId}`, () => {
        test.beforeEach(async ({page,products, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
        });
        test(`[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus} on pay for order page"`, async ({ page, products, context, baseURL
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
            const methodNeedsMoreQuantity = ['in3', 'klarnasliceit', 'alma'];
            if(methodNeedsMoreQuantity.includes(methodId) ){
                quantity = 10;
            }
            const orderData = await createManualOrder(page, products.simple.id, quantity, country, postcode, baseURL);
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
            await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context), baseURL);
        });
    });
});
