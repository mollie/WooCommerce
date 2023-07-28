const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');

test.describe('_Mollie Settings tab - Advanced', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });

//TestId-C3347
    test.skip('[C3347] Validate that the ecommerce admin can change the Description sent to Mollie regarding the order generated', async ({page}) => {
        // Your code here...
    });

test.skip('[C420152] Validate that Mollie Advanced section is displayed per UI design', async ({ page}) => {
  // Your code here...
});


test.skip('[C420148] Validate that order status after cancelled payment is set to pending status', async ({ page}) => {
  // Your code here...
});


test.skip('[C420149] Validate that order status after cancelled payment is set to cancelled status', async ({ page}) => {
  // Your code here...
});


test.skip('[C420153] Validate change of the payment screen language', async ({ page}) => {
  // Your code here...
});


test.skip('[C3332] Validate that the ecommerce admin can activate the use of Single-Click purchase', async ({ page}) => {
  // Your code here...
});


test.skip('[C420154] Validate correct gateways shown with Order API on Classic checkout', async ({ page}) => {
  // Your code here...
});


test.skip('[C420155] Validate correct gateways shown with Order API on Block checkout', async ({ page}) => {
  // Your code here...
});


test.skip('[C420156] Validate correct gateways shown with Order API on order pay page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420157] Validate correct gateways shown with Payment API on Classic checkout', async ({ page}) => {
  // Your code here...
});


test.skip('[C420158] Validate correct gateways shown with Payment  API on Block checkout', async ({ page}) => {
  // Your code here...
});


test.skip('[C420159] Validate correct gateways shown with Payment API on order pay page', async ({ page}) => {
  // Your code here...
});


test.skip('[C3367] Validate the creation of an order using the Orders API', async ({ page}) => {
  // This is duplicated all transactions tests are done using orders api
});


test.skip('[C3368] Validate the creation of an order using the Payments API', async ({ page}) => {
  // Your code here...
});


test.skip('[C420160] Validate change of the API Payment description', async ({ page}) => {
  // Your code here...
});


test.skip('[C420161] Validate change of the Surcharge gateway fee label on classic checkout', async ({ page}) => {
  // Your code here...
});


test.skip('[C420162] Validate change of the Surcharge gateway fee label on block checkout', async ({ page}) => {
  // Your code here...
});


test.skip('[C420163] Validate change of the Surcharge gateway fee label on order pay page', async ({ page}) => {
  // Your code here...
});




test.skip('[C5813] Validate that merchant can clear Mollie data from database using clear now function', async ({ page}) => {
  // Your code here...
});


test.skip('[C420164] Validate that merchant can clear Mollie data from database on plugin uninstall', async ({ page}) => {
  // Your code here...
});


});
