const {expect} = require('@playwright/test');
const {test} = require('../Shared/base-test');
const {gotoWooPaymentTab} = require("../Shared/wooUtils");
const {getMethodNames} = require("../Shared/gateways");

test.describe(' - WooCommerce Payments tab', () => {
    test.beforeEach(async ({page}) => {
        await gotoWooPaymentTab(page);
    });

//TestId-C419984
    test('Validate that all payment methods are displayed per UI design', async ({page}) => {
        const methodNames = getMethodNames();
        const locator = page.locator('a.wc-payment-gateway-method-title');
        const allMethodsPresent = await locator.evaluateAll((elements, names) => {
            const displayedMethods = elements.map((element) => {
                let methodName = element.textContent.trim();
                methodName = methodName.replace('Mollie - ', '');
                return methodName;
            });
            const foundMethods = names.map((name) => {
                return displayedMethods.includes(name);
            });
            return foundMethods.every((found) => found === true);
        }, methodNames);
        expect(allMethodsPresent).toBe(true);
    });

//TestId-C419985
    test.skip('Validate that all payment methods can be managed', async ({page}) => {
        // This will be tested in the settings tab of every payment method
    });

//TestId-C3324
    test.skip('Validate that the order of the payment methods can be changed', async ({page}) => {
        // This is a functionality of WooCommerce, not of the plugin
    });
});
