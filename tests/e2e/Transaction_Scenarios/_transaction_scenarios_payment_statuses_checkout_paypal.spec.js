const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('_Transaction scenarios_Payment statuses Checkout - PayPal', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C3392
test.skip('Validate the submission of an order with Paypal as payment method and payment mark as "Paid"', async ({ page}) => {
  // Your code here...
});


//TestId-C3393
test.skip('Validate the submission of an order with Paypal as payment method and payment mark as "Pending"', async ({ page}) => {
  // Your code here...
});


//TestId-C3394
test.skip('Validate the submission of an order with Paypal as payment method and payment mark as "Failed"', async ({ page}) => {
  // Your code here...
});


//TestId-C3395
test.skip('Validate the submission of an order with Paypal as payment method and payment mark as "Cancelled"', async ({ page}) => {
  // Your code here...
});


//TestId-C3396
test.skip('Validate the submission of an order with Paypal as payment method and payment mark as "Expired"', async ({ page}) => {
  // Your code here...
});


});
