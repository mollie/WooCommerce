// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAdmin } = require('../Shared/wpUtils');
const {setOrderAPI, markPaidInMollie} = require('../Shared/mollieUtils');
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid} = require('../Shared/testMollieInWooPage');


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

        // IN MOLLIE
        // Capture order number in Mollie and mark as paid
        const mollieOrder = await markPaidInMollie(page);

        // WOOCOMMERCE ORDER PAID PAGE
        await wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway);

        // WOOCOMMERCE ORDER PAGE
        await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
  });
});
