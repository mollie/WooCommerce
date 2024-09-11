const { expect } = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');

test.describe('_Transaction scenarios - PayPal button - Block Cart', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });


test.skip('[C420180] Validate that PayPal button is displayed per UI design in block cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420181] Validate that PayPal button is hidden when usupported product type is in the block cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420182] Validate the submission of an order with Paypal as payment method and payment mark as "Paid" from block cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420183] Validate the submission of an order with Paypal as payment method and payment mark as "Pending" from block cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420184] Validate the submission of an order with Paypal as payment method and payment mark as "Failed" from block cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420185] Validate the submission of an order with Paypal as payment method and payment mark as "Cancelled" from block cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420186] Validate the submission of an order with Paypal as payment method and payment mark as "Expired" from block cart', async ({ page}) => {
  // Your code here...
});


});
