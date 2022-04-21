
import { test, expect } from '@playwright/test';


const GATEWAYS = {
    'banktransfer':{
        'title':'Bank Transfer',

    }
}

const PRODUCTS = {
    'simple': {
        'name': 'simple_taxes',
        'price': '24,33€'
    }
}
async function loginAdmin(page: Page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-login.php');
    await page.locator('#user_pass').fill(process.env.ADMIN_PASS);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log in').click()
    ]);
}
async function setOrderAPI(page: Page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
    await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'order')
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}
async function setPaymentAPI(page: Page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
    await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'payment')
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}
async function classicCheckoutTransaction(page: Page, testedProduct, testedGateway) {
    // Go to shop
    await page.goto(process.env.E2E_URL_TESTSITE + '/shop/');
    // Add product to cart

    const productCartButton = testedProduct.name;
    await page.locator('[data-product_sku="' + productCartButton + '"]').click();

    // Go to checkout
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        await page.locator('text=Checkout').first().click()
    ]);

    await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout/');
    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    // Fill input[name="billing_first_name"]
    await page.locator('input[name="billing_first_name"]').fill('Test');
    // Fill input[name="billing_last_name"]
    await page.locator('input[name="billing_last_name"]').fill('test');

    // Check testedGateway option NO ISSUERS DROPDOWN
    await page.locator('text=' + testedGateway.title).check();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieHeader = await page.innerText('.header__info');
    const mollieOrder = mollieHeader.substring(6, mollieHeader.length)
    await page.locator('text=Paid').click();
    await page.locator('text=Continue').click();

    // WOOCOMMERCE ORDER PAID PAGE
    // Check order number
    await expect(page.locator('li.woocommerce-order-overview__order.order')).toContainText(mollieOrder);
    // Check total amount in order
    await expect(page.locator('li.woocommerce-order-overview__total.total')).toContainText(totalAmount);
    // Check customer in billind details
    await expect(page.locator('div.woocommerce-column.woocommerce-column--1.woocommerce-column--billing-address.col-1 > address')).toContainText("Test test");
    // Check Mollie method appears
    await expect(page.locator('li.woocommerce-order-overview__payment-method.method')).toContainText(testedGateway.title);

    // WOOCOMMERCE ORDER PAGE
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Processing");
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText('Order completed using Mollie – ' + testedGateway.title + ' payment');
}

test.describe('Transaction in classic checkout', () => {
    test('Transaction with Order API', async ({ page }) => {
        //login as Admin
        await loginAdmin(page);
        //Set Order API
        await setOrderAPI(page);
        for ( const key in GATEWAYS){
            let testedGateway = GATEWAYS[key]
            for( const key in PRODUCTS){
                let testedProduct = PRODUCTS[key]
                await classicCheckoutTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });

    test('Transaction with Payment API', async ({ page }) => {
        //login as Admin
        await loginAdmin(page);
        //Set Payment API
        await setPaymentAPI(page);
        for ( const key in GATEWAYS){
            let testedGateway = GATEWAYS[key]
            for( const key in PRODUCTS){
                let testedProduct = PRODUCTS[key]
                await classicCheckoutTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
});


