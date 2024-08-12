const { test } = require('../../../fixtures/base-test');
const {
    settingsNames,
    noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit
} = require('../../../utils/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../../utils/sharedUrl');
const {selectOptionSetting, fillNumberSettings} = require("../../../utils/wpUtils");
const {expect} = require("@playwright/test");

test.describe('_Mollie Settings tab_Payment method settings - Gift cards settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.giftcard;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });


test('[C130896] Validate  Gift Card  surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});


test('[C130897] Validate fixed fee for  Gift Card surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});


test('[C130898] Validate percentage fee for Gift Card surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});


test('[C130899] Validate fixed fee and percentage for  Gift Card  surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});


test('[C130900] Validate surcharge for Gift Card when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});


test('[C130901] Validate surcharge for Gift Card  when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});


test('[C130902] Validate surcharge for Gift Card when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});


test('[C130903] Validate Gift Card surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});


test('[C130904] Validate surcharge for Gift Card when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});


test('[C130905] Validate surcharge for  Gift Card  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});


});
