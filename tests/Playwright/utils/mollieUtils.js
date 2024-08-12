const {sharedUrl: {mollieSettingsTab}} = require('./sharedUrl');
const {wooOrderDetailsPageOnPaid} = require('./testMollieInWooPage');
const {normalizedName} = require("./gateways");
const {expect} = require("@playwright/test");
const {fillCustomerInCheckoutBlock, selectPaymentMethodInCheckout, captureTotalAmountCheckout,
    captureTotalAmountBlockCheckout, parseTotalAmount
} = require("./wooUtils");

const settingsNames = {
    surcharge: 'payment_surcharge',
    noFee: 'no_fee',
    fixedFee: 'fixed_fee',
    percentage: 'percentage',
    fixedFeePercentage: 'fixed_fee_percentage',
    limitFee: 'maximum_limit',
    components: 'mollie_components_enabled',
    maxLimit: 'maximum_limit',
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
    let container = await page.locator('div[data-testid="mollie-container--cardHolder"]');
    let input = await container.locator('input[data-component-type="cardHolder"][type="text"]');
    await input.fill('Name');
    container = await page.locator('div[data-testid="mollie-container--cardNumber"]');
    input = await container.locator('input[data-component-type="cardNumber"][type="text"]');
    await input.fill('4543474002249996');
    container = await page.locator('div[data-testid="mollie-container--expiryDate"]');
    input = await container.locator('input[data-component-type="expiryDate"][type="text"]');
    await input.fill('12/25');
    container = await page.locator('div[data-testid="mollie-container--verificationCode"]');
    input = await container.locator('input[data-component-type="verificationCode"][type="text"]');
    await input.fill('123');

    await page.getByRole('button', { name: 'Pay ›' }).click();
};
/**
 * Fill the credit card form and mark the status in Mollie popup
 * @param {import('@playwright/test').Page} page
 * @param status
 */
const processMollieCheckout = async (page, status) => {
    const expectedUrl = 'https://www.mollie.com/checkout/test-mode?';
    const creditCardUrl = 'https://www.mollie.com/checkout/credit-card';
    if (page.url().toString().startsWith(creditCardUrl)) {
        await fillCreditCardForm(page);
        await page.waitForTimeout(5000);
        return await markStatusInMollie(page, status);}

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
 * Clear all settings in the Mollie settings tab
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
 * It will fill the customer details in the checkout page
 * and return the total amount of the order
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 * @param productQuantity
 */
const beforePlacingOrder = async (page, testedProduct, testedGateway, productQuantity, checkoutUrl) => {

    // CUSTOMER DETAILS
    await fillCustomerInCheckoutBlock(page, testedGateway.country);

    // Check testedGateway option NO ISSUERS DROPDOWN
    const title = normalizedName(testedGateway.defaultTitle);
    await selectPaymentMethodInCheckout(page, title);
    if (testedGateway.paymentFields) {
        await page.locator(`select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_${testedGateway.id}"]`).selectOption({index: 1});
    }
    if (testedGateway.id === 'billie') {
        const billie = page.getByText('Pay by Invoice for Businesses - Billie Company *');
        await billie.locator('input[id="billing_company"]').fill('My company name');
    }
    const canFillBirthDate = await page.locator('input[name="billing_birthdate"]').first().isVisible();
    if (canFillBirthDate) {
        await page.locator('input[name="billing_birthdate"]').first().fill('1990-01-01');
    }
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);
    //Capture WooCommerce total amount
    const totalAmount = await captureTotalAmountCheckout(page);
    return totalAmount;
}

const beforePlacingOrderBlock = async (page, testedProduct, testedGateway, productQuantity, checkoutUrl) => {
    await page.goto(checkoutUrl);


    // CUSTOMER DETAILS
    await fillCustomerInCheckoutBlock(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    const title = normalizedName(testedGateway.defaultTitle);
    await page.getByText(title, { exact: true }).click();
    if (testedGateway.paymentFields) {
        await page.locator(`select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_${testedGateway.id}"]`).selectOption({index: 1});
    }
    if (testedGateway.id === 'billie') {
        const billie = page.getByText('Pay by Invoice for Businesses - Billie Company *');
        await billie.locator('input[id="billing_company"]').fill('My company name');
    }
    const canFillBirthDate = await page.locator('input[name="billing_birthdate"]').first().isVisible();
    if (canFillBirthDate) {
        await page.locator('input[name="billing_birthdate"]').first().fill('1990-01-01');
    }
    //Capture WooCommerce total amount
    const totalAmount = await captureTotalAmountBlockCheckout(page);
    await page.getByRole('button', { name: 'Place Order' }).click();
    await page.waitForTimeout(2000)
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
    let totalAmount;
    if (checkoutUrl === 'checkout-classic') {
        totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway, productQuantity, checkoutUrl);
    } else {
        totalAmount = await beforePlacingOrderBlock(page, testedProduct, testedGateway, productQuantity, checkoutUrl);
    }
    // IN MOLLIE
    // Capture order number in Mollie and mark as required
    await page.waitForTimeout(2000);
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

const noFeeAdded = async (page, method, products, expectedAmount) => {
    const result = await checkoutTransaction(page, products.simple, method)
    let received = result.totalAmount.slice(0, -1).trim();
    received = parseTotalAmount(received);
    expect(received).toEqual(expectedAmount);
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
    noFeeAdded
};
