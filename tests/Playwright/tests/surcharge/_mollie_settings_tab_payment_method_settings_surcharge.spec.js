const {test} = require('../../fixtures/base-test');
const {settingsNames, checkoutTransaction} = require('../../utils/mollieUtils');
const {addProductToCart, updateMethodSetting, selectPaymentMethodInCheckout,
    captureTotalAmountCheckout, parseTotalAmount, emptyCart
} = require("../../utils/wooUtils");
const {expect, webkit} = require("@playwright/test");
const {allMethods} = require("../../utils/gateways");
const {testData} = require("../../test-data/surcharges");

const changedNames = {
    'klarnapaylater' : 'pay later',
    'klarnapaynow' : 'pay now',
    'klarnasliceit': 'slice it',
    'in3': 'iDEAL Pay in 3 instalments',
    'ideal': 'iDEAL',
    'belfius': 'Belfius Pay Button',
    'kbc': 'KBC/CBC Payment Button',
    'banktransfer': 'Bank transfer',
    'billie': 'Pay by Invoice for Businesses - Billie',
    'sofort': 'SOFORT Banking',
}
for (const [testAction, testActionData] of Object.entries(testData)) {
    let randomMethod = testActionData.methods[0];
    let beforeAllRan = false;
    for (let [methodName, testId] of Object.entries(testActionData.methods)) {
        test(testActionData.description(testId, methodName) , async ({page, products, baseURL}) => {
            await updateMethodSetting(methodName, testActionData.payload, baseURL);
            if(!beforeAllRan) {
                await emptyCart(baseURL);
                let productQuantity = testActionData.productQuantity;

                await addProductToCart(baseURL, products.surcharge.id, productQuantity);
                const keys = Object.keys(testActionData.methods);
                randomMethod = keys[Math.floor(Math.random() * Object.entries(testActionData.methods).length)];
                beforeAllRan = true;
            }
            await page.goto('/checkout-classic/');
            const gateway = allMethods[methodName];
            await page.selectOption('select#billing_country', gateway.country);
            if (methodName in changedNames) {
                methodName = changedNames[methodName];
            }

            await selectPaymentMethodInCheckout(page, methodName);
            let totalAmount = await captureTotalAmountCheckout(page);
            totalAmount = parseTotalAmount(totalAmount);
            let expectedAmount = testActionData.totalExpectedAmount;

            await expect(totalAmount).toEqual(expectedAmount);
            // if the method is the random method, check the full transaction
            if (methodName === randomMethod) {
                const result = await checkoutTransaction(page, products.simple, gateway, 1, "Paid", 'checkout-classic' )
                let received = result.totalAmount.slice(0, -1).trim();
                received = parseTotalAmount(received);
                expect(received).toEqual(expectedAmount);
            }
        });
    }

}
/*
test.skip('[C420161] Validate change of the Surcharge gateway fee label on classic checkout', async ({ page}) => {
    // Your code here...
});

test.skip('[C420162] Validate change of the Surcharge gateway fee label on block checkout', async ({ page}) => {
    // Your code here...
});

test.skip('[C420163] Validate change of the Surcharge gateway fee label on order pay page', async ({ page}) => {
    // Your code here...
});

*/
