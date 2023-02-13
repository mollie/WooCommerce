// @ts-check
const {expect} = require('@playwright/test');
const {test} = require('../Shared/base-test');
const {setOrderAPI, setPaymentAPI, markStatusInMollie, insertAPIKeys, resetSettings} = require('../Shared/mollieUtils');
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid, wooOrderRetryPage, wooOrderDetailsPageOnFailed, wooOrderCanceledPage, wooOrderDetailsPageOnCanceled} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInCheckout} = require('../Shared/wooUtils');
const {sharedUrl: {mollieSettingsTab}} = require('../Shared/sharedUrl');

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 */
async function beforePlacingOrder(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct.name);
    await page.goto('/checkout/');

    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    await page.locator(`text=${testedGateway.defaultTitle}`).click();
    if (testedGateway.paymentFields) {
        await page.locator(`select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_${testedGateway.id}"]`).selectOption({index: 1});
    }
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
    await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function classicCheckoutCancelledTransactionPending(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Canceled");
    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderRetryPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function classicCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Canceled");
    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderCanceledPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnCanceled(page, mollieOrder, testedGateway);
}

async function classicCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway) {
    await beforePlacingOrder(page, testedProduct, testedGateway);
    const mollieOrder = await markStatusInMollie(page, "Paid");
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
    await page.locator('text=This order is no longer editable. Refund >> button').click();
    await page.locator('input[class="refund_order_item_qty"]').fill('1');
    page.on('dialog', dialog => dialog.accept());
    await page.getByRole('button', {name: 'Mollie'}).click();
    await expect(page.locator('#select2-order_status-container')).toContainText("Refunded");
}

async function classicCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway) {
    await beforePlacingOrder(page, testedProduct, testedGateway);
    const mollieOrder = await markStatusInMollie(page, "Paid");
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
    await page.locator('text=This order is no longer editable. Refund >> button').click();
    await page.locator('input[class="refund_order_item_qty"]').fill('0.5');
    page.on('dialog', dialog => dialog.accept());
    await page.locator('#woocommerce-order-items > div.inside > div.wc-order-data-row.wc-order-refund-items.wc-order-data-row-toggle > div.refund-actions > button.button.button-primary.do-api-refund').click();
    await expect(page.locator('#select2-order_status-container')).toContainText("Processing");
    await expect(page.getByText('EUR9.90 refunded')).toBeVisible();
}
test.describe.configure({ mode: 'serial' });
test.describe('Transaction in classic checkout', () => {
    const failedGateways = ['ideal', 'paypal', 'creditcard'];
    test('Transaction classic with Order API paid', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransaction(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Order API cancelled setting as pending', async ({page, products, gateways}) => {
        //setting as pending
        await page.goto(mollieSettingsTab + '&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionPending(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Order API failed setting pending', async ({page, products, gateways}) => {
        //only the gateways that support failed transactions in testing
        for (const gateway in gateways) {
            if (failedGateways.includes(gateways[gateway].id)) {
                for (const product in products) {
                    await classicCheckoutFailedTransaction(page, products[product], gateways[gateway]);
                }// end loop products
            }
        }// end loop gateways
    });
    test('Transaction classic full refund Order', async ({page, products, gateways}) => {
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionFullRefund(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic partial refund Order', async ({page, products, gateways}) => {
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionPartialRefund(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Payment API paid', async ({page, products, gateways}) => {
        //Set Payment API
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransaction(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Payment API cancelled setting as pending', async ({page, products, gateways}) => {
        //setting as pending
        await page.goto(mollieSettingsTab + '&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionPending(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic with Payment API cancelled setting as cancelled', async ({page, products, gateways}) => {
        //setting as cancelled
        await page.goto(mollieSettingsTab + '&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'cancelled');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutCancelledTransactionCancelled(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic full refund Payment', async ({page, products, gateways}) => {
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionFullRefund(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction classic partial refund Payment', async ({page, products, gateways}) => {
        for (const gateway in gateways) {
            for (const product in products) {
                await classicCheckoutPaidTransactionPartialRefund(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
});
