const { expect } = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');

test.describe('_Transaction scenarios - Apple Pay button  - Product page', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });


test.skip('[C420199] Validate that Apple Pay  button is displayed per UI design on the product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420200] Validate that Apple Pay button is hidden when visiting usupported product type page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420201] Validate the submission of an order with Apple Pay button as payment method and payment mark as "Paid" from product page', async ({ page}) => {
  // Your code here...
});


test.skip('[C420202] Validate that Apple Pay button is hidden if simple virtual product is out of stock', async ({ page}) => {
  // Your code here...
});


test.skip('[C420203] Validate that Apple Pay button is hidden if variable virtual product is out of stock', async ({ page}) => {
  // Your code here...
});


test.skip('[C420204] Validate that Apple Pay button is hidden for non-selected variation', async ({ page}) => {
  // Your code here...
});


});
