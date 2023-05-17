const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('_Transaction scenarios_Payment statuses - Block Checkout - in3', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C420219
test.skip('Validate the submission of an order with IN3 as payment method and payment mark as "Paid" on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420220
test.skip('Validate the submission of an order with IN3 as payment method and payment mark as "Failed"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420221
test.skip('Validate the submission of an order with IN3 as payment method and payment mark as "Cancelled"  on block checkout', async ({ page}) => {
  // Your code here...
});


//TestId-C420222
test.skip('Validate the submission of an order with IN3 as payment method and payment mark as "Expired"  on block checkout', async ({ page}) => {
  // Your code here...
});


});
