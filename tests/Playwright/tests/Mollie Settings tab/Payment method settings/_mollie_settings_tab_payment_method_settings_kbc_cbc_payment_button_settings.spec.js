const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');

test.describe('_Mollie Settings tab_Payment method settings - KBC_CBC Payment Button settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.kbc;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test('[C133668] Validate  KBC_CBC  surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C133669] Validate fixed fee for  KBC_CBC surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C133670] Validate percentage fee for KBC_CBC  surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C133671] Validate fixed fee and percentage for  KBC_CBC  surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C133672] Validate surcharge for KBC_CBC when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C133673] Validate surcharge for KBC_CBC when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C133674] Validate surcharge for KBC_CBC  when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test('[C133675] Validate KBC_CBC surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});


test('[C133676] Validate surcharge for KBC_CBC  when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C133677] Validate surcharge for  KBC_CBC  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
