const wooUrls = {
    settingsPaymentTab: '/wp-admin/admin.php?page=wc-settings&tab=checkout'
}
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
    const canRemove = await page.locator('text=Remove').isVisible();
    if (canRemove) {
        await page.locator('text=Remove').click();
    }
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

const placeOrderCheckout = async (page) => {
    // Click text=Place order
    await page.locator('text=Place order').click()
}


module.exports = {addProductToCart, fillCustomerInCheckout, fillCustomerInBlockCheckout, gotoWooPaymentTab, placeOrderCheckout, emptyCart}
