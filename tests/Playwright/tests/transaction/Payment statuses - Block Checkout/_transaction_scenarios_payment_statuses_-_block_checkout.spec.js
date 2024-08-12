const { test } = require('../../../fixtures/base-test');
const {normalizedName} = require("../../../utils/gateways");
const {checkoutTransaction, resetSettings, insertAPIKeys} = require("../../../utils/mollieUtils");
const {wooOrderDetailsPage} = require("../../../utils/testMollieInWooPage");
const {sharedUrl} = require("../../../utils/sharedUrl");
const {emptyCart, addProductToCart} = require("../../../utils/wooUtils");
const {blockCheckoutTransaction} = require("../../../test-data/block-checkout-transaction");

test.describe('Preconditions', async () => {
    test('Reset settings', async ({page, baseURL}) => {
        await resetSettings(page);
        await insertAPIKeys(page);
    });
});

blockCheckoutTransaction.forEach(({methodId, testId, mollieStatus, wooStatus, notice, action}) => {
    test.describe(`_Transaction scenarios_Payment statuses - Block Checkout - ${methodId}`, () => {
        let productQuantity = 1;
        test.beforeEach(async ({page, context, gateways}) => {
            context.method = gateways[methodId];
            context.methodName = normalizedName(context.method.defaultTitle);
        });
        test(
            `[${testId}] Validate the submission of an order with ${methodId} as payment method and payment mark as "${mollieStatus} on block checkout"`,
            async ({page, products, context, baseURL, canListenWebhooks}
            ) => {
                test.slow();
                await emptyCart(baseURL);
                const methodNeedsMoreQuantity = ['in3', 'klarnasliceit', 'alma'];
                if(methodNeedsMoreQuantity.includes(methodId) ){
                    productQuantity = 10;
                }
                await addProductToCart(baseURL, products.simple.id, productQuantity);
                await page.goto('/checkout/');
            const result = await checkoutTransaction(page, products.simple, context.method, productQuantity, mollieStatus, 'checkout');
            await action(page, result, context);
            if (canListenWebhooks) {
                await wooOrderDetailsPage(page, result.mollieOrder, context.method, wooStatus, notice(context), baseURL);
            }
        });
    });
});
