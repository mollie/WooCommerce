const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    fixedFeeAndPercentageOverLimit, percentageFeeOverLimit, fixedFeeOverLimit,
    fixedAndPercentageUnderLimit, percentageFeeUnderLimitTest, fixedFeeUnderLimitTest, fixedAndPercentageFeeTest,
    percentageFeeTest, fixedFeeTest, noFeeAdded
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');

test.describe('_Mollie Settings tab_Payment method settings - Klarna Pay later settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.klarnapaylater;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test('[C130871] Validate  Klarna Pay later surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C130873] Validate fixed fee for  Klarna Pay later surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C130875] Validate percentage fee for Klarna Pay later surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C130876] Validate fixed fee and percentage for  Klarna Pay later surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C130880] Validate surcharge for Klarna Pay later when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C130881] Validate surcharge for Klarna Pay later  when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C130882] Validate surcharge for Klarna Pay later when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test('[C130883] Validate Klarna Pay later surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});


test('[C130884] Validate surcharge for  Klarna Pay later when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C130885] Validate surcharge for  Klarna Pay later  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
