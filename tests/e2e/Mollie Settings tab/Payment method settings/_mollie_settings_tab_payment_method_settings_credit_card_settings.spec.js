const { test } = require('../../Shared/base-test');
const {
    settingsNames,
    classicCheckoutTransaction
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {selectOptionSetting, fillNumberSettings} = require("../../Shared/wpUtils");

test.describe('_Mollie Settings tab_Payment method settings - Credit card settings', () => {
    test.beforeEach(async ({page, context, gateways}) => {
        context.method = gateways.creditcard;
        context.tabUrl = gatewaySettingsRoot + context.method.id;
        await page.goto(context.tabUrl);
        context.surchargeSetting = settingsNames.surcharge(context.method.id);
    });
   

test.skip('[C3331] Validate that the ecommerce admin can activate the use of Mollie Components', async ({ page}) => {
  // Your code here...
});


test.skip('[C3730] Validate that the ecommerce admin has activated Mollie Components by default for new installations', async ({ page}) => {
  // Your code here...
});


test.skip('[C89350] Validate Credit card surcharge with no Fee, no fee will be added to total', async ({ page, products, context}) => {
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'no_fee');
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = result.totalAmount.slice(0, -1).trim();
    let expected = products.simple.price.slice(0, -1).trim();
    expect(expected).toEqual(total);
});


test('[C89351] Validate percentage fee for Credit card surcharge', async ({ page, products, context}) => {
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


test('[C89352] Validate fixed fee and percentage for Credit card surcharge', async ({ page, products, context}) => {
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


test('[C89353] Validate Credit card surcharge for fixed fee if  surcharge limit in € is setup, gateway fee will not be added if surcharge exceeded limit', async ({ page, products, context}) => {
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


test('[C89354] Validate surcharge for Credit card when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page, products, context}) => {
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


test('[C89355] Validate surcharge for Credit card  when is selected fixed fee and percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
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


test('[C89356] Validate surcharge for Credit card when is selected percentage fee for payment surcharge and Surcharge only under this limit in € is setup, surcharge will no be added for price above limit', async ({ page, products, context}) => {
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


test.skip('[C89357] Validate surcharge for Credit card if merchant change currency in store, currency is also changed in surcharge section for Mollie payment method', async ({ page}) => {
  // Your code here...
});


test('[C94865] Validate fixed fee for Credit card surcharge', async ({ page, products, context}) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page,fixedFeeSetting, context.tabUrl, fee);
    const result = await classicCheckoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^d.-]/g, "")) + fee;
    expect(total).toEqual(expected);
});


test.skip('[C100198] Validate surcharge for Credit card when is selected percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page}) => {
  // Your code here...
});


test.skip('[C100199] Validate surcharge for Credit card when is selected fixed fee and percentage for payment surcharge and Surcharge only under this limit in € is setup, surcharge will  be added for total under  limit', async ({ page}) => {
  // Your code here...
});


test.skip('[C94864] Validate expiry time for Credit card', async ({ page}) => {
  // Your code here...
});


});
