const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('_Transaction scenarios_Shipping and tax - Flat rate and excluding tax', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C420087
test.skip('Make a successful payment with random item excluding tax and shipping flat rate using Order API', async ({ page}) => {
  // Your code here...
});


//TestId-C420088
test.skip('Make a successful payment with random item excluding tax and shipping flat rate using Payment API', async ({ page}) => {
  // Your code here...
});


});
