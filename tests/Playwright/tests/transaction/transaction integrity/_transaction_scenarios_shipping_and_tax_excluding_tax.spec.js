const { expect } = require('@playwright/test');
const { test } = require('../../../fixtures/base-test');

test.describe('_Transaction scenarios_Shipping and tax - Excluding tax', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });


//TestId-C420083
test.skip('Make a successful payment with random item excluding tax using Order API', async ({ page}) => {
  // Your code here...
});


//TestId-C420084
test.skip('Make a successful payment with random item excluding tax using Payment API', async ({ page}) => {
  // Your code here...
});


});
