/**
 * @param {import('@playwright/test').Page} page
 * @param testedProductName
 */
const addProductToCart = async (page, testedProductName) => {
    // Go to shop
    await page.goto(process.env.E2E_URL_TESTSITE + '/shop/');
    // Add product to cart
    await page.locator('[data-product_sku="' + testedProductName + '"]').click()
}

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInCheckout = async (page) => {
    await page.locator('input[name="billing_first_name"]').fill('Julia');
    await page.locator('input[name="billing_last_name"]').fill('Callas');
    await page.selectOption('select#billing_country', 'DE');
    await page.locator('input[name="billing_city"]').fill('Berlin');
    await page.locator('input[name="billing_address_1"]').fill('Calle Drutal');
    await page.locator('input[name="billing_postcode"]').fill('22100');
    await page.locator('input[name="billing_phone"]').fill('1234566788');
    await page.locator('input[name="billing_email"]').fill('test@test.com');
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

module.exports = {addProductToCart, fillCustomerInCheckout, fillCustomerInBlockCheckout}
