const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('_Transaction scenarios_Payment statuses Checkout - Klarna Pay later', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C3401
test.skip('Validate the submission of an order with Klarna Pay Later as payment method and payment mark as "Authorized"', async ({ page}) => {
  // Your code here...
});


//TestId-C3402
test.skip('Validate the submission of an order with Klarna Pay Later as payment method and payment mark as "Failed"', async ({ page}) => {
  // Your code here...
});


//TestId-C3403
test.skip('Validate the submission of an order with Klarna Pay Later as payment method and payment mark as "Cancelled"', async ({ page}) => {
  // Your code here...
});


//TestId-C3404
test.skip('Validate the submission of an order with Klarna Pay Later as payment method and payment mark as "Expired"', async ({ page}) => {
  // Your code here...
});


});
