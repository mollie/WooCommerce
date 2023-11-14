const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');

test.describe('_Transaction scenarios_Different cards - American Express', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

//TestId-C420129
test.skip('Make a successful payment with American Express card using Order API', async ({ page}) => {
  // Your code here...
});


//TestId-C420130
test.skip('Make a successful payment with American Express card using Payment API', async ({ page}) => {
  // Your code here...
});


//TestId-C420216
test.skip('Make a successful payment with American Express card using Order API and Mollie Components', async ({ page}) => {
  // Your code here...
});


//TestId-C420215
test.skip('Make a successful payment with American Express card using Payment API and Mollie Components', async ({ page}) => {
  // Your code here...
});


});
