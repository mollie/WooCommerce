const { expect } = require('@playwright/test');
const { test } = require('../../Shared/base-test');

test.describe('_Transaction scenarios - PayPal button - Classic Cart', () => {
  test.beforeEach(async ({ page }) => {
    //code before each
  });
   

test.skip('[C420175] Validate that PayPal button is displayed per UI design in classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420173] Validate that PayPal button is hidden when usupported product type is in the classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420174] Validate the submission of an order with Paypal as payment method and payment mark as "Paid" from classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420176] Validate the submission of an order with Paypal as payment method and payment mark as "Pending"  from classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420177] Validate the submission of an order with Paypal as payment method and payment mark as "Failed"  from classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420178] Validate the submission of an order with Paypal as payment method and payment mark as "Cancelled"  from classic cart', async ({ page}) => {
  // Your code here...
});


test.skip('[C420179] Validate the submission of an order with Paypal as payment method and payment mark as "Expired"  from classic cart', async ({ page}) => {
  // Your code here...
});


});
