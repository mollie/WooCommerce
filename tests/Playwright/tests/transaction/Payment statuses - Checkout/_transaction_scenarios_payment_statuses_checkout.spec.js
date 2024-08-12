const { test } = require('../../../fixtures/base-test');
const {
    checkoutTransaction
} = require('../../../utils/mollieUtils');
const {wooOrderDetailsPage} = require("../../../utils/testMollieInWooPage");
const {normalizedName} = require("../../../utils/gateways");
const {emptyCart, addProductToCart} = require("../../../utils/wooUtils");
const {classicCheckoutTransaction} = require("../../../test-data/classic-checkout-transaction");



classicCheckoutTransaction.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses Checkout - ${methodId}`, () => {
        let productQuantity = 1;
        test.beforeEach(async ({browser, page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);

        });
        test(
            `[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus}"`,
            async ({page,products,context,baseURL, gateways, canListenWebhooks}
            ) => {
                test.slow();
                await emptyCart(baseURL);
                const methodNeedsMoreQuantity = ['in3', 'klarnasliceit', 'alma', 'klarnapaylater', 'klarnapaynow'];
                if(methodNeedsMoreQuantity.includes(methodId) ){
                    productQuantity = 10;
                }
            await addProductToCart(baseURL, products.simple.id, productQuantity);
            await page.goto('/checkout-classic/');
            const result = await checkoutTransaction(page, products.simple, gateways[methodId], productQuantity, mollieStatus, 'checkout-classic');
            await action(page, result, context);

               await wooOrderDetailsPage(page, result.mollieOrder, gateways[methodId], wooStatus, notice(context), baseURL);

        });
        test.afterEach(async ({page, context}) => {
            await context.close()
        });

    });
});
/*test.skip('[C420148] Validate that order status after cancelled payment is set to pending status', async ({ page}) => {
    await page.selectOption('select[name="mollie-payments-for-woocommerce_order_status_cancelled_payments"]', 'pending');
    await page.click('text=Save changes');
});

test.skip('[C420149] Validate that order status after cancelled payment is set to cancelled status', async ({ page}) => {
    await page.selectOption('select[name="mollie-payments-for-woocommerce_order_status_cancelled_payments"]', 'cancelled');
    await page.click('text=Save changes');
});
test.skip('[C3367] Validate the creation of an order using the Orders API', async ({page}) => {
    // This is duplicated all transactions tests are done using orders api
});
test.skip('[C3368] Validate the creation of an order using the Payments API', async ({page}) => {
    // This is duplicated we have already tests for this
});*/
