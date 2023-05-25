const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    fixedFeeAndPercentageOverLimit, percentageFeeOverLimit, fixedFeeOverLimit,
    fixedAndPercentageUnderLimit, percentageFeeUnderLimitTest, fixedFeeUnderLimitTest, fixedAndPercentageFeeTest,
    percentageFeeTest, fixedFeeTest, noFeeAdded
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');

test.describe('_Mollie Settings tab_Payment method settings - MyBank settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.mybank;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test('[C420319] Validate MyBank surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C420320] Validate fixed fee for MyBank surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C420321] Validate percentage fee for MyBank surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C420322] Validate fixed fee and percentage for MyBank surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C420323] Validate surcharge for MyBank when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C420324] Validate surcharge for MyBank when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C420325] Validate surcharge for MyBank when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test.skip('[C420326] Validate MyBank surcharge for fixed fee if surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
  await fixedFeeOverLimit(page, context, products);
});


test('[C420327] Validate surcharge for MyBank when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C420328] Validate surcharge for MyBank  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
