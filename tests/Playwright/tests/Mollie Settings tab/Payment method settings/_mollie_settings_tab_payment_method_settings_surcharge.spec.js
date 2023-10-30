const {test} = require('../../Shared/base-test');
const {
    setOrderAPI,
    insertAPIKeys,
    resetSettings,
    settingsNames,
    noFeeAdded, fixedFeeTest, percentageFeeTest, fixedAndPercentageFeeTest,
    fixedFeeUnderLimitTest, percentageFeeUnderLimitTest, fixedAndPercentageUnderLimit, fixedFeeOverLimit,
    percentageFeeOverLimit, fixedFeeAndPercentageOverLimit, noticeLines
} = require('../../Shared/mollieUtils');
const {sharedUrl: {gatewaySettingsRoot}} = require('../../Shared/sharedUrl');
const {wooOrderPaidPage} = require("../../Shared/testMollieInWooPage");
const {addProductToCart, updateMethodSetting} = require("../../Shared/wooUtils");
// Set up parameters or perform actions before all tests
/*test.beforeAll(async ({browser}) => {
    // Create a new page instance
    const page = await browser.newPage();
    // Reset to the default state
    await resetSettings(page);
    await insertAPIKeys(page);
    // Orders API
    await setOrderAPI(page);
});*/

// create array of actions
const testData = {
    'noFeeAdded': {
        'description': `[${testId}] Validate ${methodId} surcharge with no Fee, no fee will be added to total`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.noFee,
            }
        },
        'methods': {
            'bancontact': 'C129502'
        },
    },
    'fixedFeeTest': {
        'description': `[${testId}] Validate fixed fee for ${methodId} surcharge`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFee,
                [settingsNames.fixedFee]: 10
            }
        },
        'methods': {
            'bancontact': 'C129503'
        }
    },
    'percentageFeeTest': {
        'description': `[${testId}] Validate percentage fee for ${methodId} surcharge`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.percentage,
                [settingsNames.percentage]: 10
            }
        },
        'methods': {
            'bancontact': 'C129504'
        }
    },
    'fixedAndPercentageFeeTest': {
        'description': `[${testId}] Validate fixed fee and percentage for ${methodId} surcharge`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFeePercentage,
                [settingsNames.fixedFee]: 10,
                [settingsNames.percentage]: 10
            }
        },
        'methods': {
            'bancontact': 'C129505'
        }
    },
    'fixedFeeUnderLimitTest': {
        'description': `[${testId}] Validate surcharge for ${methodId} when is selected fixed fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFee,
                [settingsNames.fixedFee]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'methods': {
            'bancontact': 'C129506'
        }
    },
    'percentageFeeUnderLimitTest': {
        'description': `[${testId}] Validate surcharge for ${methodId} when is selected percentage fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.percentage,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'methods': {
            'bancontact': 'C129507'
        }
    },
    'fixedAndPercentageUnderLimit': {
        'description': `[${testId}] Validate surcharge for ${methodId} when is selected fixed and percentage fee for payment surcharge and surcharge only under this limit in € is setup, surcharge will  be added for total under  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFeePercentage,
                [settingsNames.fixedFee]: 10,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'methods': {
            'bancontact': 'C129508'
        }
    },
    'fixedFeeOverLimit': {
        'description': `[${testId}] Validate surcharge for ${methodId} when is selected fixed fee for payment surcharge and surcharge only over this limit in € is setup, surcharge will  be added for total over  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFee,
                [settingsNames.fixedFee]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'methods': {
            'bancontact': 'C129509'
        }
    },
    'percentageFeeOverLimit': {
        'description': `[${testId}] Validate surcharge for ${methodId} when is selected percentage fee for payment surcharge and surcharge only over this limit in € is setup, surcharge will  be added for total over  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.percentage,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'methods': {
            'bancontact': 'C129510'
        }
    },
    'fixedFeeAndPercentageOverLimit': {
        'description': `[${testId}] Validate surcharge for ${methodId} when is selected fixed and percentage fee for payment surcharge and surcharge only over this limit in € is setup, surcharge will  be added for total over  limit`,
        'payload': {
            "settings": {
                [settingsNames.surcharge]: settingsNames.fixedFeePercentage,
                [settingsNames.fixedFee]: 10,
                [settingsNames.percentage]: 10,
                [settingsNames.limitFee]: 30
            }
        },
        'methods': {
            'bancontact': 'C129511'
        }
    }
}
for (const [testAction, value] of Object.entries(testData)) {
    // set all methods with surcharge setting
    for (const [methodName, testId] of Object.entries(testData.methods)) {
        await updateMethodSetting(methodName, testData.payload);
    }

    // put item in cart
    await addProductToCart(page, context._options.baseURL, products.simple.id, productQuantity);

    // go to checkout
    await page.goto('/checkout/');
    // loop through all methods and test
    // select a random method
    const methods = testData.methods;
    const randomMethod = methods[Math.floor(Math.random() * methods.length)];
    // validate surcharge
    await testAction(page, randomMethod, testData);
};


test.skip('[C93487] Validate expiry time for Bancontact', async ({page}) => {
    // Your code here...
});

