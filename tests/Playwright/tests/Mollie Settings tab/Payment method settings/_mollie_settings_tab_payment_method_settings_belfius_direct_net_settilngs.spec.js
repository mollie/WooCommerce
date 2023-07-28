const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    checkoutTransaction, noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {selectOptionSetting, fillNumberSettings} = require("../../Shared/wpUtils");

test.describe('_Mollie Settings tab_Payment method settings - Belfius Direct Net settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.belfius;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test('[C138011] Validate  Belfius Direct Net surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C138012] Validate fixed fee for Belfius Direct Net surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C138013] Validate percentage fee for Belfius Direct Net  surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C138014] Validate fixed fee and percentage for  Belfius Direct Net  surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C138015] Validate surcharge for Belfius Direct Net when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C138016] Validate surcharge for Belfius Direct Net when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C138017] Validate surcharge for Belfius Direct Net when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test('[C138018] Validate Belfius Direct Net surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});


test('[C138019] Validate surcharge for Belfius Direct Net  when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C138020] Validate surcharge for  Belfius Direct Net  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
