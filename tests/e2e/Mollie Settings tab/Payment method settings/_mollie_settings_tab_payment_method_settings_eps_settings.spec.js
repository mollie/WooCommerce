const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    classicCheckoutTransaction, noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {selectOptionSetting, fillNumberSettings} = require("../../Shared/wpUtils");
const {expect} = require("@playwright/test");

test.describe('_Mollie Settings tab_Payment method settings - EPS settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.eps;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test('[C133658] Validate  EPS  surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C133659] Validate fixed fee for  EPS  surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C133660] Validate percentage fee for EPS  surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C133661] Validate fixed fee and percentage for  EPS  surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C133662] Validate surcharge for EPS when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C133663] Validate surcharge for EPS  when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C133664] Validate surcharge for EPS  when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test('[C133665] Validate EPS  surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});


test('[C133666] Validate surcharge for EPS  when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C133667] Validate surcharge for  EPS   when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
