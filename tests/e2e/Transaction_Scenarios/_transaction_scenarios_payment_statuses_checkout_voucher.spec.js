const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('_Transaction scenarios_Payment statuses Checkout - Voucher', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C3435
test.skip('Validate the submission of an order with a ECO Voucher and marked as "Paid"', async ({ page}) => {
  // Your code here...
});


//TestId-C3436
test.skip('Validate the submission of an order with a MEAL Voucher and marked as "Paid"', async ({ page}) => {
  // Your code here...
});


//TestId-C3437
test.skip('Validate the submission of an order with a GIFT Voucher and marked as "Paid"', async ({ page}) => {
  // Your code here...
});


//TestId-C3440
test.skip('Validate the submission of an order with any payment method including a Voucher and marked as "Paid"', async ({ page}) => {
  // Your code here...
});


//TestId-C3441
test.skip('Validate the submission of an order were the total value is paid with a Voucher', async ({ page}) => {
  // Your code here...
});


});
