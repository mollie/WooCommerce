// @ts-check
const {expect} = require('@playwright/test');
const {test} = require('../Shared/base-test');
const {setOrderAPI, setPaymentAPI, markStatusInMollie} = require('../Shared/mollieUtils');
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid, wooOrderRetryPage} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInCheckout} = require('../Shared/wooUtils');

async function beforePlacingOrder(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct.name);

    // Go to checkout
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        await page.locator('text=Checkout').first().click()
    ]);

    await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout');
    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN

    await page.locator('#payment_method_mollie_wc_gateway_' + testedGateway.id).check();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);
    return totalAmount;
}

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 */
async function classicCheckoutPaidTransaction(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Paid");

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
}

async function classicCheckoutFailedTransaction(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Failed");

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderRetryPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    //await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function classicCheckoutCancelledTransactionPending(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Canceled");
}

async function classicCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Canceled");
}

async function classicCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway) {
    await classicCheckoutPaidTransaction(page, testedProduct, testedGateway);
        //in order page select quantity
    //refund
}

async function classicCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway) {
    await classicCheckoutPaidTransaction(page, testedProduct, testedGateway);
    //in order page select partial amount
    //refund
}


test.describe('Transaction in classic checkout', () => {
    test('Transaction classic with Order API paid', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Order API failed', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutFailedTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Order API cancelled setting as pending', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        //setting as pending
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionPending(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Order API cancelled setting as cancelled', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        //setting as cancelled
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionCancelled(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic full refund Order', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionFullRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic partial refund Order', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionPartialRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Payment API paid', async ({page, products, gateways}) => {
        //Set Payment API
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Payment API cancelled setting as pending', async ({page, products, gateways}) => {
        //Set Payment API
        await setPaymentAPI(page);
        //setting as pending
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionPending(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Payment API cancelled setting as cancelled', async ({page, products, gateways}) => {
        //Set Payment API
        await setPaymentAPI(page);
        //setting as cancelled
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionCancelled(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic full refund Payment', async ({page, products, gateways}) => {
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionFullRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic partial refund Payment', async ({page, products, gateways}) => {
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionPartialRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
});
