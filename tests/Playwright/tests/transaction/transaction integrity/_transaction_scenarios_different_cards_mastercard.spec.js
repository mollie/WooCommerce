const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');

test.describe('_Transaction scenarios_Different cards - Mastercard', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C420127
test.skip('Make a successful payment with Mastercard card using Order API', async ({ page}) => {
  // Your code here...
});


//TestId-C420128
test.skip('Make a successful payment with Mastercard card using Payment API', async ({ page}) => {
  // Your code here...
});


//TestId-C420213
test.skip('Make a successful payment with Mastercard card using Order API and Mollie Components', async ({ page}) => {
  // Your code here...
});


//TestId-C420214
test.skip('Make a successful payment with Mastercard card using Payment API and Mollie Components', async ({ page}) => {
  // Your code here...
});


});
