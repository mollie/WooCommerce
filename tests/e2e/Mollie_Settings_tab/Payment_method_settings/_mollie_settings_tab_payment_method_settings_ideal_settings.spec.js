const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    classicCheckoutTransaction
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {selectOptionSetting, fillNumberSettings} = require("../../Shared/wpUtils");
const {expect} = require("@playwright/test");

test.describe('_Mollie Settings tab_Payment method settings - iDEAL settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.bancontact;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

//TestId-C3362
test.skip('Validate that the iDEAL issuer list available in payment selection', async ({ page}) => {
  // Your code here...
});


//TestId-C89358
test.skip('Validate expiry time for IDEAL', async ({ page}) => {
  // Your code here...
});


//TestId-C130856
test('Validate iDEAL surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'no_fee');
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = result.totalAmount.slice(0, -1).trim();
    let expected = products.simple.price.slice(0, -1).trim();
    expect(expected).toEqual(total);
});


//TestId-C130857
test('Validate fixed fee for  iDEAL surcharge', async ({ page, products, context}) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, "")) + fee;
    expect(total).toEqual(expected);
});


//TestId-C130858
test('Validate percentage fee for iDEAL surcharge', async ({ page, products, context}) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'percentage');
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page,percentageFeeSetting, context.tabUrl, fee);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = productPrice + (productPrice * fee/100);
    expect(total).toEqual(expected);
});


//TestId-C130859
test('Validate fixed fee and percentage for  iDEAL surcharge', async ({ page, products, context}) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee_percentage');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page,percentageFeeSetting, context.tabUrl, fee);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = productPrice + fee + (productPrice * fee/100);
    expect(total).toEqual(expected);
});


//TestId-C130860
test('Validate surcharge for iDEAL when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
    const fee = 10;
    const limit = 30;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page,limitFeeSetting, context.tabUrl, limit);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, "")) + fee;
    expect(total).toEqual(expected);
});


//TestId-C130861
test('Validate surcharge for iDEAL  when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    const fee = 10;
    const limit = 30;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'percentage');
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page,percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page,limitFeeSetting, context.tabUrl, limit);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = productPrice + (productPrice * fee/100);
    expect(total).toEqual(expected);
});


//TestId-C130862
test('Validate surcharge for iDEAL when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will be added for total under limit', async ({ page, products, context}) => {
    const fee = 10;
    const limit = 30;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee_percentage');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page,percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page,limitFeeSetting, context.tabUrl, limit);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = productPrice + fee + (productPrice * fee/100);
    expect(total).toEqual(expected);
});


//TestId-C130863
test('Validate iDEAL surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
    const fee = 10;
    const limit = 30;
    const productQuantity = 2;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page,limitFeeSetting, context.tabUrl, limit);
    const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, "")) * productQuantity;
    expect(total).toEqual(expected);
});


//TestId-C130864
test('Validate surcharge for iDEAL when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    const fee = 10;
    const limit = 30;
    const productQuantity = 2;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'percentage');
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page,percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page,limitFeeSetting, context.tabUrl, limit);
    const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, ""))  * productQuantity;
    expect(total).toEqual(productPrice);
});


//TestId-C130865
test('Validate surcharge for iDEAL  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
    const fee = 10;
    const limit = 30;
    const productQuantity = 2;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee_percentage');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page,percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page,limitFeeSetting, context.tabUrl, limit);
    const result = await classicCheckoutTransaction(page, products.simple, context.method, productQuantity)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, ""))  * productQuantity;
    expect(total).toEqual(productPrice);
});


});
