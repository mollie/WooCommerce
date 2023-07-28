const path = require("path");
const fs = require("fs");
const {manualOrder} = require("./manualOrder");
const wooUrls = {
    settingsPaymentTab: '/wp-admin/admin.php?page=wc-settings&tab=checkout'
}
const WooCommerceRestApi = require("@woocommerce/woocommerce-rest-api").default;
// import WooCommerceRestApi from "@woocommerce/woocommerce-rest-api"; // Supports ESM
async function gotoWPPage(page, url) {
    await page.goto(url);
}
async function gotoWooPaymentTab(page) {
    await gotoWPPage(page, wooUrls.settingsPaymentTab);
}
/**
 * @param {import('@playwright/test').Page} page
 * @param productSku
 */
const addProductToCart = async (page, productSku) => {
    await page.goto('/shop/');
    await page.locator('[data-product_sku="' + productSku + '"].add_to_cart_button').click()
}

const emptyCart = async (page) => {
    await page.goto('/cart/');
    const canRemove = await page.getByRole('cell', { name: 'Remove this item' }).isVisible();
    if (canRemove) {
        await page.getByRole('cell', { name: 'Remove this item' }).click();
    }
}

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInCheckout = async (page, country = "DE") => {
    await page.locator('input[name="billing_first_name"]').fill('Julia');
    await page.locator('input[name="billing_last_name"]').fill('Callas');
    await page.selectOption('select#billing_country', country);
    await page.locator('input[name="billing_city"]').fill('Berlin');
    await page.locator('input[name="billing_address_1"]').fill('Calle Drutal');
    await page.locator('input[name="billing_postcode"]').fill('22100');
    await page.locator('input[name="billing_phone"]').fill('1234566788');
    await page.locator('input[name="billing_email"]').fill('test@test.com');
    const canFillCompany = await page.locator('input[name="billing_company"]').isVisible();
    if (canFillCompany) {
        await page.locator('input[name="billing_company"]').fill('Test company');
    }
    const canFillBirthDate = await page.locator('input[name="billing_birthdate"]').isVisible();
    if (canFillBirthDate) {
        await page.locator('input[name="billing_birthdate"]').fill('01-01-1990');
    }
}

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInCheckoutBlock = async (page, country = 'Germany') => {
    await page.getByLabel('First name').fill('Julia');
    await page.getByLabel('Last name').fill('Callas');
    await page.getByLabel('Country/Region').fill(country);
    await page.getByLabel('City').fill('Berlin');
    await page.getByLabel('Address', { exact: true }).fill('Calle Drutal');
    await page.getByLabel('Postal code').fill('22100');
    await page.getByLabel('Phone').fill('1234566788');
    await page.getByLabel('Email address').fill('test@test.com');
    const canFillCompany = await page.getByLabel('Company').isVisible();
    if (canFillCompany) {
        await page.getByLabel('Company').fill('Test company');
    }
    const canFillBirthDate = await page.locator('input[name="billing_birthdate"]').isVisible();
    if (canFillBirthDate) {
        await page.locator('input[name="billing_birthdate"]').fill('01-01-1990');
    }
}

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInBlockCheckout = async (page) => {
    // Fill input[name="billing_first_name"]
    await page.locator('input[name="billing_first_name"]').fill('Julia');
    // Fill input[name="billing_last_name"]
    await page.locator('input[name="billing_last_name"]').fill('Callas');
}

const selectPaymentMethodInCheckout = async (page, paymentMethod) => {
    await page.getByText(paymentMethod, { exact: true }).click();
}

const placeOrderCheckout = async (page) => {
    // Click text=Place order
    await page.locator('text=Place order').click()
}

const placeOrderPayPage = async (page) => {
    // Click text=Place order
    await page.getByRole('button', { name: 'Pay for order' }).click()
}

const captureTotalAmountCheckout = async (page) => {
    return await page.innerText('.order-total > td > strong > span > bdi');
}

const captureTotalAmountPayPage = async (page) => {
    return await page.innerText('.woocommerce-Price-amount.amount > bdi');
}

const captureTotalAmountBlockCheckout = async (page) => {
    let totalLine = await page.locator('div').filter({ hasText: /^Total/ }).first()
    let totalAmount = await totalLine.innerText('.woocommerce-Price-amount amount > bdi');
    // totalAmount is "Total\n72,00 €" and we need to remove the "Total\n" part
    return totalAmount.substring(6, totalAmount.length);
}


const WooCommerce = new WooCommerceRestApi({
    url: process.env.BASEURL,
    consumerKey: process.env.WOO_REST_CONSUMER_KEY,
    consumerSecret: process.env.WOO_REST_CONSUMER_SECRET,
    version: 'wc/v3'
});
const createManualOrder = async (page, productLabel = 'Beanie') => {
    try {
        const response = await WooCommerce.post("orders", manualOrder);
        const url = `/checkout/order-pay/${response.data.id}?pay_for_order=true&key=${response.data.order_key}`;
        return {
            url: url,
            orderId: response.data.id,
            orderKey: response.data.order_key
        };
    } catch (error) {
        console.log(error.response.data);
    }
}

const getLogByName = async (name, dirname) => {
    const currentDate = new Date().toISOString().split('T')[0];
    // Construct the relative path to the log file
    const logsDirectory = path.join(dirname, '..', '..', '..', '.ddev', 'wordpress', 'wp-content', 'uploads', 'wc-logs');
    const files = fs.readdirSync(logsDirectory);
    const matchingFiles = files.filter(file => file.includes(`${name}-${currentDate}-`));
    // Select the first matching file
    const logFileName = matchingFiles[0];
    const logFilePath = path.join(logsDirectory, logFileName);
    return fs.readFileSync(logFilePath, 'utf-8');
}

module.exports = {
    addProductToCart,
    fillCustomerInCheckout,
    fillCustomerInBlockCheckout,
    fillCustomerInCheckoutBlock,
    gotoWooPaymentTab,
    placeOrderCheckout,
    emptyCart,
    placeOrderPayPage,
    selectPaymentMethodInCheckout,
    captureTotalAmountCheckout,
    captureTotalAmountBlockCheckout,
    captureTotalAmountPayPage,
    createManualOrder,
    getLogByName
}
