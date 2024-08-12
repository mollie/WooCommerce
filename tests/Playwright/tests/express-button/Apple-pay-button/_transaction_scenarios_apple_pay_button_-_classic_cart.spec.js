const { expect } = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');

test.describe('_Transaction scenarios - Apple Pay button - Classic Cart', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });


test.skip('[C420205] Validate that Apple Pay button is displayed per UI design in classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420206] Validate that Apple Pay button is hidden when usupported product type is in the classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420207] Validate the submission of an order with Apple Pay button as payment method and payment mark as "Paid" from classic cart', async ({ page}) => {
  // Your code here...
});


});
