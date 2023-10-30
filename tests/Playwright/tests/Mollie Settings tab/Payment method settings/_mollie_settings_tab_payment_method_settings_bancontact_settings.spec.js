const { test } = require('../../Shared/base-test');
const {
    setOrderAPI,
    insertAPIKeys,
    resetSettings,
    settingsNames,
    noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
// Set up parameters or perform actions before all tests
/*test.beforeAll(async ({browser}) => {
    // Create a new page instance
    const page = await browser.newPage();
    // Reset to the default state
    await resetSettings(page);
    await insertAPIKeys(page);
    // Orders API
    await setOrderAPI(page);
});*/

test.describe('_Mollie Settings tab_Payment method settings - Bancontact settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.bancontact;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });

test('[C129502] Validate Bancontact surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await noFeeAdded(page, context, products);
});

test('[C129503] Validate fixed fee for Bancontact surcharge', async ({ page, products, context}) => {
    await fixedFeeTest(page, context, products);
});

test('[C129504] Validate percentage fee for Bancontact surcharge', async ({ page, products, context}) => {
    await percentageFeeTest(page, context, products);
});

test('[C129505] Validate fixed fee and percentage for Bancontact surcharge', async ({ page, products, context}) => {
    await fixedAndPercentageFeeTest(page, context, products);
});

test('[C129506] Validate surcharge for Bancontact when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    await fixedFeeUnderLimitTest(page, context, products);
});

test('[C129798] Validate surcharge for Bancontact when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await percentageFeeUnderLimitTest(page, context, products);
});

test('[C129799] Validate surcharge for Bancontact when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    await fixedAndPercentageUnderLimit(page, context, products);
});

test('[C129800] Validate Bancontact surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    await fixedFeeOverLimit(page, context, products);
});

test('[C129801] Validate surcharge for Bancontact when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await percentageFeeOverLimit(page, context, products);
});

test('[C129802] Validate surcharge for Bancontact  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    await fixedFeeAndPercentageOverLimit(page, context, products);
});

test.skip('[C93487] Validate expiry time for Bancontact', async ({ page}) => {
  // Your code here...
});
});
