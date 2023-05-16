const {expect, webkit} = require('@playwright/test');
const {test} = require('../../Shared/base-test');
const {settingsNames, classicCheckoutTransaction} = require('../../Shared/mollieUtils');
const {sharedUrl: {mollieSettingsTab, gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {selectOptionSetting} = require("../../Shared/wpUtils");

test.describe('_Mollie Settings tab_Payment method settings - Apple Pay settings', () => {
    // Force Webkit browser for all tests within this suite
    test.use(webkit);

//TestId-C420309
    test.skip('Validate Apple Pay surcharge with no Fee, no fee will be added to total', async ({page, products, gateways}) => {
        //there seems to be a problem with the automation of the Apple Pay payment method tests
        const method = gateways.applepay;
        const tabUrl = gatewaySettingsRoot + method.id;
        await page.goto(tabUrl);
        const settingName = settingsNames.surcharge(method.id);
        await selectOptionSetting(page, settingName, tabUrl, 'no_fee');
        const result = await classicCheckoutTransaction(page, products.simple, method)
        expect(result.amount).toBe(products.simple.price);
    });


//TestId-C420310
    test.skip('Validate fixed fee for Apple Pay surcharge', async ({page}) => {
        // Your code here...
    });


//TestId-C420311
    test.skip('Validate percentage fee for Apple Pay surcharge', async ({page}) => {
        // Your code here...
    });


//TestId-C420312
    test.skip('Validate fixed fee and percentage for Apple Pay surcharge', async ({page}) => {
        // Your code here...
    });


//TestId-C420313
    test.skip('Validate surcharge for Apple Pay when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({page}) => {
        //
    });


//TestId-C420314
    test.skip('Validate surcharge for Apple Pay when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({page}) => {
        // Your code here...
    });


//TestId-C420315
    test.skip('Validate surcharge for Apple Pay when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({page}) => {
        // Your code here...
    });


//TestId-C420316
    test.skip('Validate Apple Pay surcharge for fixed fee if surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({page}) => {
        // Your code here...
    });


//TestId-C420317
    test.skip('Validate surcharge for Apple Pay when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({page}) => {
        // Your code here...
    });


//TestId-C420318
    test.skip('Validate surcharge for Apple Pay when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({page}) => {
        // Your code here...
    });


});
