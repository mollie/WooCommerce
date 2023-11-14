const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');

test.describe(' - Transaction scenarios', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

test.skip('[C3329] Validate that the ecommerce admin can change the payment methods to display based on the user location', async ({ page}) => {
  // Your code here...
});


test.skip('[C3358] Validate only the activated payment methods are displayed on the checkout screen', async ({ page}) => {
  // Your code here...
});


test.skip('[C3361] Validate only the correct payment methods are displayed based on the billing country', async ({ page}) => {
  // Your code here...
});


test.skip('[C3363] Validate the order is created before the payment was successful', async ({ page}) => {
  // Your code here...
});


test.skip('[C3364] Validate the order is created after the payment was successful', async ({ page}) => {
  // Your code here...
});


});
