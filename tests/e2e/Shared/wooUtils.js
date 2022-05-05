/**
 * @param {import('@playwright/test').Page} page
 */
const addProductToCart = async (page, testedProduct) => {
    // Go to shop
    await page.goto(process.env.E2E_URL_TESTSITE + '/shop/');
    // Add product to cart
    const productCartButton = testedProduct.name;
    await page.locator('[data-product_sku="' + productCartButton + '"]').click();
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
