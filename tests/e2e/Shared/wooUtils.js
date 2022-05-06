/**
 * @param {import('@playwright/test').Page} page
 * @param testedProductName
 */
const addProductToCart = async (page, testedProductName) => {
    // Go to shop
    await page.goto(process.env.E2E_URL_TESTSITE + '/shop/');
    // Add product to cart
    await page.locator('[data-product_sku="' + testedProductName + '"]').click();
}

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInCheckout = async (page) => {
    // Fill input[name="billing_first_name"]
    await page.locator('input[name="billing_first_name"]').fill('Test');
    // Fill input[name="billing_last_name"]
    await page.locator('input[name="billing_last_name"]').fill('test');
}

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInBlockCheckout = async (page) => {
    // Fill input[name="billing_first_name"]
    await page.locator('input[name="billing_first_name"]').fill('Test');
    // Fill input[name="billing_last_name"]
    await page.locator('input[name="billing_last_name"]').fill('test');
}

module.exports = {addProductToCart, fillCustomerInCheckout, fillCustomerInBlockCheckout}
