// @ts-check
const {expect} = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {setOrderAPI, setPaymentAPI, markPaidInMollie} = require('../Shared/mollieUtils');
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInBlockCheckout} = require('../Shared/wooUtils');

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 */
async function blockCheckoutPaidTransaction(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct);

    // Go to checkout
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        await page.locator('text=Checkout').first().click()
    ]);
    await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout/');

    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInBlockCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    await page.locator('text=' + testedGateway.title).check();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markPaidInMollie(page);

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
}

async function blockCheckoutFailedTransaction(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct);

    // Go to checkout
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        await page.locator('text=Checkout').first().click()
    ]);

    await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout/');
    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInBlockCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    await page.locator('text=' + testedGateway.title).check();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markFailedInMollie(page);

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderRetryPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function blockCheckoutCancelledTransactionPending(page, testedProduct, testedGateway) {

}

async function blockCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway) {

}

async function blockCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway) {
    await blockCheckoutPaidTransaction(page, testedProduct, testedGateway);
        //in order page select quantity
    //refund
}

async function blockCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway) {
    await blockCheckoutPaidTransaction(page, testedProduct, testedGateway);
    //in order page select partial amount
    //refund
}

async function blockCheckoutExpiredTransaction(page, testedProduct, testedGateway) {

}

test.describe('Transaction in block checkout', () => {
    test('Transaction block with Order API paid', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Order API failed', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutFailedTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Order API cancelled setting as pending', async ({page, products, gateways}) => {
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
                await blockCheckoutCancelledTransactionPending(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Order API cancelled setting as cancelled', async ({page, products, gateways}) => {
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
                await blockCheckoutCancelledTransactionCancelled(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block full refund Order', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransactionFullRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block partial refund Order', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransactionPartialRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Order API expired', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutExpiredTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API paid', async ({page, products, gateways}) => {
        //Set Payment API
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API cancelled setting as pending', async ({page, products, gateways}) => {
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
                await blockCheckoutCancelledTransactionPending(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API cancelled setting as cancelled', async ({page, products, gateways}) => {
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
                await blockCheckoutCancelledTransactionCancelled(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block full refund Payment', async ({page, products, gateways}) => {
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransactionFullRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block partial refund Payment', async ({page, products, gateways}) => {
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransactionPartialRefund(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API expired', async ({page, products, gateways}) => {
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutExpiredTransaction(page, product, gateway);
            }// end loop products
        }// end loop gateways
    });
});
