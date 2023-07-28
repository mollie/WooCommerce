const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');

test.describe('_Mollie Settings tab_Payment method settings - SEPA Bank Transfer settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.banktransfer;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test.skip('[C127228] Validate expiry time for SEPA Bank Transfer', async ({ page}) => {
  // Your code here...
});


test('[C136529] Validate  Bank Transfer surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C136530] Validate fixed fee for  Bank Transfer surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C136531] Validate percentage fee for Bank Transfer  surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C136532] Validate fixed fee and percentage for  Bank Transfer  surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C136533] Validate surcharge for Bank Transfer when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C136534] Validate surcharge for Bank Transfer when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C136535] Validate surcharge for Bank Transfer  when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test('[C136536] Validate Bank Transfer surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});


test('[C136537] Validate surcharge for Bank Transfer  when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C136538] Validate surcharge for  Bank Transfer  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
