const { expect } = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');

test.describe('_Transaction scenarios - PayPal button - Product page', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });


test.skip('[C420187] Validate that PayPal button is displayed per UI design on the product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420188] Validate that PayPal button is hidden when visiting usupported product type page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420189] Validate the submission of an order with Paypal as payment method and payment mark as "Paid" from product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420190] Validate the submission of an order with Paypal as payment method and payment mark as "Pending" from product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420191] Validate the submission of an order with Paypal as payment method and payment mark as "Failed" from product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420192] Validate the submission of an order with Paypal as payment method and payment mark as "Cancelled" from product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420193] Validate the submission of an order with Paypal as payment method and payment mark as "Expired" from product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420194] Validate that PayPal button is hidden if simple virtual product is out of stock', async ({ page}) => {
  // Your code here...
});


test.skip('[C420195] Validate that PayPal button is hidden if variable virtual product is out of stock', async ({ page}) => {
  // Your code here...
});


test.skip('[C420196] Validate that PayPal button is hidden for non-selected variation', async ({ page}) => {
  // Your code here...
});


});
