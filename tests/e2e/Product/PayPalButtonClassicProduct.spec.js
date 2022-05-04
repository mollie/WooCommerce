// @ts-check
const { test, expect } = require('@playwright/test');
const GATEWAYS = {
  'paypal':{
      'title':'PayPal',

  }
}
const PRODUCTS = {
  'simple': {
      'name': 'simple_taxes',
      'price': '24,33€'
  },
    'virtual': {
        'name': 'virtual_no_down',
        'price': '20,25€'
    }
}
/**
 * @param {import('@playwright/test').Page} page
 */
async function loginAdmin(page) {
  await page.goto(process.env.E2E_URL_TESTSITE + '/wp-login.php');
  await page.locator('#user_pass').fill(process.env.ADMIN_PASS);
  await Promise.all([
      page.waitForNavigation(),
      page.locator('text=Log in').click()
  ]);
}
/**
 * @param {import('@playwright/test').Page} page
 */
async function setOrderAPI(page) {
  await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
  await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'order')
  await Promise.all([
      page.waitForNavigation(),
      page.locator('text=Save changes').click()
  ]);
}
/**
 * @param {import('@playwright/test').Page} page
 */
async function setPaymentAPI(page) {
  await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
  await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'payment')
  await Promise.all([
      page.waitForNavigation(),
      page.locator('text=Save changes').click()
  ]);
}

test.describe('PayPal Transaction in classic product', () => {
    test.beforeAll(async ({browser }) => {
        const page = await browser.newPage();
        await loginAdmin(page);

    });
    test('Not be seen if not enabled', async ({ page }) => {
        // Go to virtual product page
        await page.goto(process.env.E2E_URL_TESTSITE + '/product/virtual_no_down/');
        await expect(page.locator('#mollie-PayPal-button')).not.toBeVisible();

    });
    test('Not be seen if not virtual', async ({ page }) => {
        // set PayPal visible in cart
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=mollie_wc_gateway_paypal');
        await page.locator('input[name="mollie_wc_gateway_paypal_mollie_paypal_button_enabled_cart"]').check();
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        // Go to simple product
        await page.goto(process.env.E2E_URL_TESTSITE + '/product/simple_taxes');
        await expect(page.locator('#mollie-PayPal-button')).not.toBeVisible();
    });
    test('Transaction with Order API - virtual product', async ({ page }) => {
      let testedGateway = GATEWAYS.paypal;
      await loginAdmin(page);
      await setOrderAPI(page);
        // Go to virtual product page
        await page.goto(process.env.E2E_URL_TESTSITE + '/product/virtual_no_down/');

      await expect(page.locator('#mollie-PayPal-button')).toBeVisible();
      //Capture WooCommerce total amount
      const totalAmount = PRODUCTS.virtual.price
      await Promise.all([
          page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=paypal&token=3.q6wq1i' }*/),
          page.locator('input[alt="PayPal Button"]').click()
      ]);

      // Check paid with Mollie
      const mollieHeader = await page.innerText('.header__info');
      const mollieOrder = mollieHeader.substring(6, mollieHeader.length)
      await page.locator('text=Paid').click();
      await page.locator('text=Continue').click();


      await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout/order-received/639/?key=wc_order_DO3CVhQvzpCxv&utm_nooverride=1');
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
  });
});
