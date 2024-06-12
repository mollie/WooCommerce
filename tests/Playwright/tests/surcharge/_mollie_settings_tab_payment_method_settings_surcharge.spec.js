const {test} = require('../Shared/base-test');
const {settingsNames, checkoutTransaction} = require('../Shared/mollieUtils');
const {addProductToCart, updateMethodSetting, selectPaymentMethodInCheckout,
    captureTotalAmountCheckout, parseTotalAmount, emptyCart
} = require("../Shared/wooUtils");
const {expect, webkit} = require("@playwright/test");
const {allMethods} = require("../Shared/gateways");

const testData = {
    'noFeeAdded': {
        'description': (testId, methodName) => `[${testId}] Validate ${methodName} surcharge with no Fee, no fee will be added to total`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.noFee,
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 11.00,
        'methods': {
            'bancontact': 'C129502',
            //'applepay': 'C420309',
            'belfius': 'C138011',
            //'billie': 'C354664',
            'eps': 'C133658',
            'giropay': 'C136539',
            'ideal': 'C130856',
            'kbc': 'C133668',
            'klarnapaylater': 'C130871',
            'klarnapaynow': 'C136519',
            'klarnasliceit': 'C127227',
            'mybank': 'C420319',
            'paypal': 'C130886',
            'paysafecard': 'C420131',
            'przelewy24': 'C129803',
            'banktransfer': 'C136529',
            'sofort': 'C129201',
            'in3': 'C106908',
        },
    },
    'fixedFeeTest': {
        'description': (testId, methodName) => `[${testId}] Validate fixed fee for ${methodName} surcharge`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFee,
                [settingsNames.fixedFee]: 10
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 22.00,
        'methods': {
            'bancontact': 'C129503',
            //'applepay': 'C420310',
            'belfius': 'C138012',
            //'billie': 'C354665',
            'eps': 'C133659',
            'giropay': 'C136540',
            'ideal': 'C130857',
            'kbc': 'C133669',
            'klarnapaylater': 'C130873',
            'klarnapaynow': 'C136520',
            'klarnasliceit': 'C127817',
            'mybank': 'C420320',
            'paypal': 'C130887',
            'paysafecard': 'C420132',
            'przelewy24': 'C129804',
            'banktransfer': 'C136530',
            'sofort': 'C129493',
            'in3': 'C106909',
        }
    },
    'percentageFeeTest': {
        'description': (testId, methodName) => `[${testId}] Validate percentage fee for ${methodName} surcharge`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.percentage,
                [settingsNames.percentage]: 10
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 34.19,
        'methods': {
            'bancontact': 'C129504',
            //'applepay': 'C420311',
            'belfius': 'C138013',
            //'billie': 'C354666',
            'eps': 'C133660',
            'giropay': 'C136541',
            'ideal': 'C130858',
            'kbc': 'C133670',
            'klarnapaylater': 'C130875',
            'klarnapaynow': 'C136521',
            'klarnasliceit': 'C127818',
            'mybank': 'C420321',
            'paypal': 'C130888',
            'paysafecard': 'C420133',
            'przelewy24': 'C129805',
            'banktransfer': 'C136531',
            'sofort': 'C129494',
            'in3': 'C106910',
        }
    },
    'fixedAndPercentageFeeTest': {
        'description': (testId, methodName) => `[${testId}] Validate fixed fee and percentage for ${methodName} surcharge`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFeePercentage,
                [settingsNames.fixedFee]: 10,
                [settingsNames.percentage]: 10
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 45.19,
        'methods': {
            'bancontact': 'C129505',
            //'applepay': 'C420312',
            'belfius': 'C138014',
            //'billie': 'C354667',
            'eps': 'C133661',
            'giropay': 'C136542',
            'ideal': 'C130859',
            'kbc': 'C133671',
            'klarnapaylater': 'C130876',
            'klarnapaynow': 'C136522',
            'klarnasliceit': 'C127819',
            'mybank': 'C420322',
            'paypal': 'C130889',
            'paysafecard': 'C420134',
            'przelewy24': 'C129806',
            'banktransfer': 'C136532',
            'sofort': 'C129495',
            'in3': 'C106911',
        }
    },
    'fixedFeeUnderLimitTest': {
        'description': (testId, methodName) => `[${testId}] Validate surcharge for ${methodName} when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFee,
                [settingsNames.fixedFee]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 41.80,
        'methods': {
            'bancontact': 'C129506',
            //'applepay': 'C420313',
            'belfius': 'C138015',
            //'billie': 'C354668',
            'eps': 'C133662',
            'giropay': 'C136543',
            'ideal': 'C130860',
            'kbc': 'C133672',
            'klarnapaylater': 'C130880',
            'klarnapaynow': 'C136523',
            'klarnasliceit': 'C1278120',
            'mybank': 'C420323',
            'paypal': 'C130890',
            'paysafecard': 'C420135',
            'przelewy24': 'C129807',
            'banktransfer': 'C136533',
            'sofort': 'C129496',
            'in3': 'C106912',
        }
    },
    'percentageFeeUnderLimitTest': {
        'description': (testId, methodName) => `[${testId}] Validate surcharge for ${methodName} when is selected percentage fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.percentage,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 34.19,
        'methods': {
            'bancontact': 'C129507',
            //'applepay': 'C420314',
            'belfius': 'C138016',
            //'billie': 'C354669',
            'eps': 'C133663',
            'giropay': 'C136544',
            'ideal': 'C130861',
            'kbc': 'C133673',
            'klarnapaylater': 'C130881',
            'klarnapaynow': 'C136524',
            'klarnasliceit': 'C1278121',
            'mybank': 'C420324',
            'paypal': 'C130891',
            'paysafecard': 'C420136',
            'przelewy24': 'C129808',
            'banktransfer': 'C136534',
            'sofort': 'C129497',
            'in3': 'C106913',
        }
    },
    'fixedAndPercentageUnderLimit': {
        'description': (testId, methodName) => `[${testId}] Validate surcharge for ${methodName} when is selected fixed and percentage fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFeePercentage,
                [settingsNames.fixedFee]: 10,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'productQuantity': 1,
        'totalExpectedAmount': 45.19,
        'methods': {
            'bancontact': 'C129508',
            //'applepay': 'C420315',
            'belfius': 'C138017',
            //'billie': 'C354670',
            'eps': 'C133664',
            'giropay': 'C136545',
            'ideal': 'C130862',
            'kbc': 'C133674',
            'klarnapaylater': 'C130882',
            'klarnapaynow': 'C136525',
            'klarnasliceit': 'C1278122',
            'mybank': 'C420325',
            'paypal': 'C130892',
            'paysafecard': 'C420137',
            'przelewy24': 'C129809',
            'banktransfer': 'C136535',
            'sofort': 'C129498',
            'in3': 'C106914',
        }
    },
    'fixedFeeOverLimit': {
        'description': (testId, methodName) => `[${testId}] Validate surcharge for ${methodName} when is selected fixed fee for payment surcharge and surcharge only over this limit in € is setup, surcharge will  be added for total over  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFee,
                [settingsNames.fixedFee]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'productQuantity': 3,
        'totalExpectedAmount': 70.40,
        'methods': {
            'bancontact': 'C129509',
            //'applepay': 'C420316',
            'belfius': 'C138018',
            //'billie': 'C354671',
            'eps': 'C133665',
            'giropay': 'C136546',
            'ideal': 'C130863',
            'kbc': 'C133675',
            'klarnapaylater': 'C130883',
            'klarnapaynow': 'C136526',
            'klarnasliceit': 'C128597',
            'mybank': 'C420326',
            'paypal': 'C130893',
            'paysafecard': 'C420138',
            'przelewy24': 'C129810',
            'banktransfer': 'C136536',
            'sofort': 'C129499',
            'in3': 'C106915',
        }
    },
    'percentageFeeOverLimit': {
        'description': (testId, methodName) => `[${testId}] Validate surcharge for ${methodName} when is selected percentage fee for payment surcharge and surcharge only over this limit in € is setup, surcharge will  be added for total over  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.percentage,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'productQuantity': 3,
        'totalExpectedAmount': 70.40,
        'methods': {
            'bancontact': 'C129510',
            //'applepay': 'C420317',
            'belfius': 'C138019',
            //'billie': 'C354672',
            'eps': 'C133666',
            'giropay': 'C137063',
            'ideal': 'C130864',
            'kbc': 'C133676',
            'klarnapaylater': 'C130884',
            'klarnapaynow': 'C136527',
            'klarnasliceit': 'C129200',
            'mybank': 'C420327',
            'paypal': 'C130894',
            'paysafecard': 'C420139',
            'przelewy24': 'C129811',
            'banktransfer': 'C136537',
            'sofort': 'C129500',
            'in3': 'C106916',
        }
    },
    'fixedFeeAndPercentageOverLimit': {
        'description': (testId, methodName) => `[${testId}] Validate surcharge for ${methodName} when is selected fixed and percentage fee for payment surcharge and surcharge only over this limit in € is setup, surcharge will  be added for total over  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFeePercentage,
                [settingsNames.fixedFee]: 10,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'productQuantity': 3,
        'totalExpectedAmount': 70.40,
        'methods': {
            'bancontact': 'C129511',
            //'applepay': 'C420318',
            'belfius': 'C138020',
            //'billie': 'C354673',
            'eps': 'C133667',
            'giropay': 'C137322',
            'ideal': 'C130865',
            'kbc': 'C133677',
            'klarnapaylater': 'C130885',
            'klarnapaynow': 'C136528',
            'klarnasliceit': 'C106918',
            'mybank': 'C420328',
            'paypal': 'C130895',
            'paysafecard': 'C420140',
            'przelewy24': 'C129812',
            'banktransfer': 'C136538',
            'sofort': 'C129501',
            'in3': 'C106917',
        }
    }
}
for (const [testAction, testActionData] of Object.entries(testData)) {
    let randomMethod = testActionData.methods[0];
    let beforeAllRan = false;
    for (const [methodName, testId] of Object.entries(testActionData.methods)) {
        test(testActionData.description(testId, methodName) , async ({page, products, baseURL}) => {
            await updateMethodSetting(methodName, testActionData.payload);
            if(!beforeAllRan) {
                await emptyCart(baseURL);
                let productQuantity = testActionData.productQuantity;
                if(methodName === 'in3' || methodName === 'klarnasliceit'){
                    productQuantity = 10;
                }
                await addProductToCart(baseURL, products.surcharge.id, productQuantity);
                const keys = Object.keys(testActionData.methods);
                randomMethod = keys[Math.floor(Math.random() * Object.entries(testActionData.methods).length)];
                console.log('randomMethod', randomMethod)
                beforeAllRan = true;
            }
            await page.goto('/checkout/');
            await
            await selectPaymentMethodInCheckout(page, methodName);
            let totalAmount = await captureTotalAmountCheckout(page);
            totalAmount = parseTotalAmount(totalAmount);
            let expectedAmount = testActionData.totalExpectedAmount;

            await expect(totalAmount).toEqual(expectedAmount);

            // if the method is the random method, check the full transaction
            if (methodName === randomMethod) {
                const gateway = allMethods[randomMethod]
                const result = await checkoutTransaction(page, products.simple, gateway)
                let received = result.totalAmount.slice(0, -1).trim();
                received = parseTotalAmount(received);
                expect(received).toEqual(expectedAmount);
            }
        });
    }

};
test.skip('[C420161] Validate change of the Surcharge gateway fee label on classic checkout', async ({ page}) => {
    // Your code here...
});

test.skip('[C420162] Validate change of the Surcharge gateway fee label on block checkout', async ({ page}) => {
    // Your code here...
});

test.skip('[C420163] Validate change of the Surcharge gateway fee label on order pay page', async ({ page}) => {
    // Your code here...
});

