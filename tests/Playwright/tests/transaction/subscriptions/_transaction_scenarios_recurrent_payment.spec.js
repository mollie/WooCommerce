const { expect } = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');

test.describe('_Transaction scenarios - Recurrent Payment', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });


test.skip('[C3348] Validate that only the correct payment methods (that supports a first mandate) are displayed for recurring products', async ({ page}) => {
  // Your code here...
});


test.skip('[C3349] Validate a recurring payment order is placed successfully', async ({ page}) => {
  // Your code here...
});


test.skip('[C3350] Validate subsequent orders are created automatically based on the sequence of the product', async ({ page}) => {
  // Your code here...
});


test.skip('[C3351] Validate that a recurrent payment can be cancelled as a logged in customer', async ({ page}) => {
  // Your code here...
});


test.skip('[C3352] Validate that a recurrent payment can be cancelled through the admin panel', async ({ page}) => {
  // Your code here...
});


test.skip('[C3353] Validate that when a subsequent order failed with payment twice the subscription gets cancelled', async ({ page}) => {
  // Your code here...
});


test.skip('[C3354] Validate the price for a subscription stays the same even if there is a product price change', async ({ page}) => {
  // Your code here...
});


test.skip('[C3355] Validate that you can retry a failed payment in a subscription', async ({ page}) => {
  // Your code here...
});


test.skip('[C3356] Validate that a customer is created and linked to the recent order in Mollie Dashboard', async ({ page}) => {
  // Your code here...
});


test.skip('[C3357] Validate multiple recurrent subscriptions in one customer', async ({ page}) => {
  // Your code here...
});


});
