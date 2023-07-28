const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('_Transaction scenarios_Versions - PHP', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

test.skip('[C419989] Validate that Mollie can’t be activated on an environment running on PHP version below 7.2', async ({ page}) => {
  // Your code here...
});


//TestId-C419990
test.skip('Create an successful transaction on an environment running on PHP version 7.2', async ({ page}) => {
  // Your code here...
});


//TestId-C419991
test.skip('Create an successful transaction on an environment running on PHP version 7.4', async ({ page}) => {
  // Your code here...
});


//TestId-C419992
test.skip('Create an successful transaction on an environment running on PHP version 8', async ({ page}) => {
  // Your code here...
});


//TestId-C419993
test.skip('Create an successful transaction on an environment running on PHP version 8.1', async ({ page}) => {
  // Your code here...
});


});
