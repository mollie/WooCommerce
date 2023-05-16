const {sharedUrl: {mollieSettingsTab}} = require('../Shared/sharedUrl');
const {loginAdmin} = require("./wpUtils");
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid, wooOrderRetryPage, wooOrderDetailsPageOnFailed, wooOrderCanceledPage, wooOrderDetailsPageOnCanceled} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInCheckout} = require('../Shared/wooUtils');
const {normalizedName} = require("./gateways");
const {expect} = require("@playwright/test");

const settingsNames = {
    surcharge: (method) => `mollie_wc_gateway_${method}_payment_surcharge`,
    fixedFee: (method) => `mollie_wc_gateway_${method}_fixed_fee`,
    percentage: (method) => `mollie_wc_gateway_${method}_percentage`,
    limitFee: (method) => `mollie_wc_gateway_${method}_maximum_limit`,
}

const noticeLines = {
    paid: (method) => `Order completed using Mollie – ${method} payment`,
    open: (method) => `Mollie – ${method} payment still pending`,
    completed: (method) => `Order completed using Mollie – ${method} payment`,
    failed: (method) => `${method} payment started`,
    canceled: (method) => `${method} payment started`,
    expired: (method) => `${method} payment started`,
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
const beforePlacingOrder = async (page, testedProduct, testedGateway, productQuantity) => {
    for (let i = productQuantity; i >0; i--) {
        await addProductToCart(page, testedProduct.sku);
    }

    await page.goto('/checkout/');

    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInCheckout(page);

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
const classicCheckoutTransaction = async (page, testedProduct, testedGateway, productQuantity = 1, status = "Paid") => {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway, productQuantity);
    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, status);

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

module.exports = {
    setOrderAPI,
    setPaymentAPI,
    markStatusInMollie,
    insertAPIKeys: insertCorrectAPIKeys,
    insertIncorrectAPIKeys,
    resetSettings,
    beforePlacingOrder,
    classicCheckoutTransaction,
    classicCheckoutPaidTransactionFullRefund,
    classicCheckoutPaidTransactionPartialRefund,
    checkExpiredAtMollie,
    settingsNames,
    noticeLines,
};
