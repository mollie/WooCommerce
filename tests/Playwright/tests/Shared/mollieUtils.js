const {sharedUrl: {mollieSettingsTab}} = require('../Shared/sharedUrl');
const {loginAdmin, selectOptionSetting, fillNumberSettings} = require("./wpUtils");
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid, wooOrderRetryPage, wooOrderDetailsPageOnFailed, wooOrderCanceledPage, wooOrderDetailsPageOnCanceled} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInCheckout} = require('../Shared/wooUtils');
const {normalizedName} = require("./gateways");
const {expect} = require("@playwright/test");
const {fillCustomerInCheckoutBlock, selectPaymentMethodInCheckout, captureTotalAmountCheckout,
    captureTotalAmountBlockCheckout
} = require("./wooUtils");

const settingsNames = {
    surcharge: (method) => `mollie_wc_gateway_${method}_payment_surcharge`,
    fixedFee: (method) => `mollie_wc_gateway_${method}_fixed_fee`,
    percentage: (method) => `mollie_wc_gateway_${method}_percentage`,
    limitFee: (method) => `mollie_wc_gateway_${method}_maximum_limit`,
    components: (method) => `mollie_wc_gateway_${method}_mollie_components_enabled`,
}

const noticeLines = {
    paid: (method) => `Order completed using Mollie - ${method} payment`,
    open: (method) => `${method} payment started`,
    completed: (method) => `Order completed using Mollie - ${method} payment`,
    failed: (method) => `${method} payment started`,
    canceled: (method) => `${method} payment started`,
    expired: (method) => `${method} payment started`,
    authorized: (method) => `Order authorized using Mollie - ${method} payment`,
}
/**
 * @param {import('@playwright/test').Page} page
 */
const setOrderAPI = async (page) => {
    await page.goto(mollieSettingsTab + '&section=advanced');
    await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'order')
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 */
const setPaymentAPI = async (page) => {
    await page.goto(mollieSettingsTab + '&section=advanced');
    await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'payment')
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 * @param status
 */
const markStatusInMollie = async (page, status) =>{
    const mollieHeader = await page.innerText('.header__info');
    const mollieOrder = mollieHeader.substring(6, mollieHeader.length)
    await page.locator('text=' + status).click();
    await page.locator('text=Continue').click();
    return mollieOrder;
}

const fillCreditCardForm = async (page) => {
    await page.getByLabel('Card holder').fill('test');


    await page.getByLabel('Card number').fill('4543474002249996');
    await page.locator('iframe[name="expiryDate-input"]').fill('12/25');
    await page.locator('iframe[name="verificationCode-input"]').fill( '123');
    await page.getByRole('button', { name: 'Pay ›' }).click();
};
const processMollieCheckout = async (page, status) => {
    const expectedUrl = 'https://www.mollie.com/checkout/test-mode?';
    const creditCardUrl = 'https://www.mollie.com/checkout/credit-card';
    if (page.url().toString().startsWith(creditCardUrl)) {
        await fillCreditCardForm(page);
        return await markStatusInMollie(page, status);
    }

    if (page.url().toString().startsWith(expectedUrl)) {
        return await markStatusInMollie(page, status);
    } else {
        // find the first button
        const button = await page.$('button');
        await button.click();
        return await markStatusInMollie(page, status);
    }
}

/**
 * @param {import('@playwright/test').Page} page
 */
const insertCorrectAPIKeys = async (page) =>{
    await page.goto(mollieSettingsTab);
    await page.locator(`input[name="mollie-payments-for-woocommerce_live_api_key"]`).fill(process.env.MOLLIE_LIVE_API_KEY);
    await page.locator(`input[name="mollie-payments-for-woocommerce_test_mode_enabled"]`).check();
    await page.locator(`input[name="mollie-payments-for-woocommerce_test_api_key"]`).fill(process.env.MOLLIE_TEST_API_KEY);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 */
const insertIncorrectAPIKeys = async (page) =>{
    await page.goto(mollieSettingsTab);
    await page.locator(`input[name="mollie-payments-for-woocommerce_live_api_key"]`).fill('live_1234567890');
    await page.locator(`input[name="mollie-payments-for-woocommerce_test_mode_enabled"]`).check();
    await page.locator(`input[name="mollie-payments-for-woocommerce_test_api_key"]`).fill('test_1234567890');
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 */
const resetSettings = async (page) => {
    await page.goto(mollieSettingsTab + '&section=advanced');
    await Promise.all([
        page.waitForNavigation(),
        await page.locator('text=clear now').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 * @param productQuantity
 */
const beforePlacingOrder = async (page, testedProduct, testedGateway, productQuantity, checkoutUrl) => {
    //Capture WooCommerce total amount
    const totalAmount = await captureTotalAmountCheckout(page);

    // CUSTOMER DETAILS
    await fillCustomerInCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    const title = normalizedName(testedGateway.defaultTitle);
    await selectPaymentMethodInCheckout(page, title);
    if (testedGateway.paymentFields) {
        await page.locator(`select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_${testedGateway.id}"]`).selectOption({index: 1});
    }
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);
    return totalAmount;
}

const beforePlacingOrderBlock = async (page, testedProduct, testedGateway, productQuantity, checkoutUrl) => {
    for (let i = productQuantity; i >0; i--) {
        await addProductToCart(page, testedProduct.sku);
    }

    await page.goto(checkoutUrl);

    //Capture WooCommerce total amount
    const totalAmount = await captureTotalAmountBlockCheckout(page);
    // CUSTOMER DETAILS
    await fillCustomerInCheckoutBlock(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    const title = normalizedName(testedGateway.defaultTitle);
    await page.getByText(title, { exact: true }).click();
    if (testedGateway.paymentFields) {
        await page.locator(`select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_${testedGateway.id}"]`).selectOption({index: 1});
    }
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);
    return totalAmount;
}

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 * @param productQuantity
 * @param status
 */
const checkoutTransaction = async (page, testedProduct, testedGateway, productQuantity = 1, status = "Paid", checkoutUrl ='/checkout/') => {
    let whichCheckout = checkoutUrl === '/checkout/' ? 'classic' : 'block';
    let totalAmount;
    if (whichCheckout === 'classic') {
        totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway, productQuantity, checkoutUrl);
    } else {
        totalAmount = await beforePlacingOrderBlock(page, testedProduct, testedGateway, productQuantity, checkoutUrl);
    }
    // IN MOLLIE
    // Capture order number in Mollie and mark as required
    const mollieOrder = await processMollieCheckout(page, status);

    return {mollieOrder: mollieOrder, totalAmount: totalAmount};
}

const classicCheckoutPaidTransactionFullRefund = async (page, testedProduct, testedGateway) => {
    await beforePlacingOrder(page, testedProduct, testedGateway);
    const mollieOrder = await markStatusInMollie(page, "Paid");
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
    await page.locator('text=This order is no longer editable. Refund >> button').click();
    await page.locator('input[class="refund_order_item_qty"]').fill('1');
    page.on('dialog', dialog => dialog.accept());
    await page.getByRole('button', {name: 'Mollie'}).click();
    await expect(page.locator('#select2-order_status-container')).toContainText("Refunded");
}

const classicCheckoutPaidTransactionPartialRefund = async (page, testedProduct, testedGateway) => {
    await beforePlacingOrder(page, testedProduct, testedGateway);
    const mollieOrder = await markStatusInMollie(page, "Paid");
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
    await page.locator('text=This order is no longer editable. Refund >> button').click();
    await page.locator('input[class="refund_order_item_qty"]').fill('0.5');
    page.on('dialog', dialog => dialog.accept());
    await page.locator('#woocommerce-order-items > div.inside > div.wc-order-data-row.wc-order-refund-items.wc-order-data-row-toggle > div.refund-actions > button.button.button-primary.do-api-refund').click();
    await expect(page.locator('#select2-order_status-container')).toContainText("Processing");
    await expect(page.getByText('EUR9.90 refunded')).toBeVisible();
}

const checkExpiredAtMollie = async (page) => {
    //this assumes the page is mollie checkout
    await expect(page.getByText('The payment has been set to expired successfully.')).toBeVisible();
}

const fixedFeeTest = async (page, context, products) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page, fixedFeeSetting, context.tabUrl, fee);
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, "")) + fee;
    expect(total).toEqual(expected);
}

const percentageFeeTest = async (page, context, products) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'percentage');
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page, percentageFeeSetting, context.tabUrl, fee);
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = productPrice + (productPrice * fee / 100);
    expect(total).toEqual(expected);
}

const fixedAndPercentageFeeTest = async (page, context, products) => {
    const fee = 10;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee_percentage');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page, fixedFeeSetting, context.tabUrl, fee);
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page, percentageFeeSetting, context.tabUrl, fee);
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = productPrice + fee + (productPrice * fee / 100);
    expect(total).toEqual(expected);
}

const fixedFeeUnderLimitTest = async (page, context, products) => {
    const fee = 10;
    const limit = 30;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page, fixedFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page, limitFeeSetting, context.tabUrl, limit);
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, "")) + fee;
    expect(total).toEqual(expected);
}

const percentageFeeUnderLimitTest = async (page, context, products) => {
    const fee = 10;
    const limit = 30;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'percentage');
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page, percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page, limitFeeSetting, context.tabUrl, limit);
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = productPrice + (productPrice * fee / 100);
    expect(total).toEqual(expected);
}

const fixedAndPercentageUnderLimit = async (page, context, products) => {
    const fee = 10;
    const limit = 30;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee_percentage');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page, fixedFeeSetting, context.tabUrl, fee);
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page, percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page, limitFeeSetting, context.tabUrl, limit);
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = productPrice + fee + (productPrice * fee / 100);
    expect(total).toEqual(expected);
}

const fixedFeeOverLimit = async (page, context, products) => {
    const fee = 10;
    const limit = 30;
    const productQuantity = 2;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page, fixedFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page, limitFeeSetting, context.tabUrl, limit);
    const result = await checkoutTransaction(page, products.simple, context.method, productQuantity);
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let expected = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, "")) * productQuantity;
    expect(total).toEqual(expected);
}

const percentageFeeOverLimit = async (page, context, products) => {
    const fee = 10;
    const limit = 30;
    const productQuantity = 2;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'percentage');
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page, percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page, limitFeeSetting, context.tabUrl, limit);
    const result = await checkoutTransaction(page, products.simple, context.method, productQuantity)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, "")) * productQuantity;
    expect(total).toEqual(productPrice);
}

const fixedFeeAndPercentageOverLimit = async (page, context, products) => {
    const fee = 10;
    const limit = 30;
    const productQuantity = 2;
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'fixed_fee_percentage');
    const fixedFeeSetting = settingsNames.fixedFee(context.method.id);
    await fillNumberSettings(page, fixedFeeSetting, context.tabUrl, fee);
    const percentageFeeSetting = settingsNames.percentage(context.method.id);
    await fillNumberSettings(page, percentageFeeSetting, context.tabUrl, fee);
    const limitFeeSetting = settingsNames.limitFee(context.method.id);
    await fillNumberSettings(page, limitFeeSetting, context.tabUrl, limit);
    const result = await checkoutTransaction(page, products.simple, context.method, productQuantity)
    let total = parseFloat(result.totalAmount.replace(",", ".").replace(/[^\d.-]/g, ""));
    let productPrice = parseFloat(products.simple.price.replace(",", ".").replace(/[^\d.-]/g, "")) * productQuantity;
    expect(total).toEqual(productPrice);
}

const noFeeAdded = async (page, context, products) => {
    await selectOptionSetting(page, context.surchargeSetting, context.tabUrl, 'no_fee');
    const result = await checkoutTransaction(page, products.simple, context.method)
    let total = result.totalAmount.slice(0, -1).trim();
    let expected = products.simple.price.slice(0, -1).trim();
    expect(expected).toEqual(total);
}

module.exports = {
    setOrderAPI,
    setPaymentAPI,
    markStatusInMollie,
    insertAPIKeys: insertCorrectAPIKeys,
    insertIncorrectAPIKeys,
    resetSettings,
    beforePlacingOrder,
    beforePlacingOrderBlock,
    checkoutTransaction,
    classicCheckoutPaidTransactionFullRefund,
    classicCheckoutPaidTransactionPartialRefund,
    checkExpiredAtMollie,
    processMollieCheckout,
    settingsNames,
    noticeLines,
    fixedFeeTest,
    percentageFeeTest,
    fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest,
    percentageFeeUnderLimitTest,
    fixedAndPercentageUnderLimit,
    fixedFeeOverLimit,
    percentageFeeOverLimit,
    fixedFeeAndPercentageOverLimit,
    noFeeAdded
};
