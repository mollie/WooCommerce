const {noticeLines, checkExpiredAtMollie} = require("../utils/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage} = require("../utils/testMollieInWooPage");
export const classicCheckoutTransaction = [
    {
        methodId: "bancontact",
        testId: "C3387",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "bancontact",
        testId: "C3388",
        mollieStatus: "Open",
        wooStatus: "Pending",
        notice: context => noticeLines.open(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "bancontact",
        testId: "C3389",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "bancontact",
        testId: "C3390",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "bancontact",
        testId: "C3391",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: 'belfius',
        testId: "C3428",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
   {
        methodId: 'belfius',
        testId: "C3429",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: 'belfius',
        testId: "C3430",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: 'belfius',
        testId: "C3431",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "billie",
        testId: "C354674",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "billie",
        testId: "C354675",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "billie",
        testId: "C354676",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "billie",
        testId: "C354677",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "eps",
        testId: "C3412",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "eps",
        testId: "C3413",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "eps",
        testId: "C3414",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "eps",
        testId: "C3415",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "in3",
        testId: "C3731",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "in3",
        testId: "C3732",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "in3",
        testId: "C3733",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "in3",
        testId: "C3734",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "ideal",
        testId: "C3382",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "ideal",
        testId: "C3383",
        mollieStatus: "Open",
        wooStatus: "Pending",
        notice: context => noticeLines.open(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "ideal",
        testId: "C3384",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "ideal",
        testId: "C3386",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "ideal",
        testId: "C3385",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            //await checkExpiredAtMollie(page); for some reason when expired Mollie API does not show the same message with ideal but goes to retry page
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "kbc",
        testId: "C3419",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "kbc",
        testId: "C3416",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "kbc",
        testId: "C3417",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "kbc",
        testId: "C3418",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C3401",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C3402",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C3403",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C3404",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C3397",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C3398",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C3399",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C3400",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C3408",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C3409",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C3410",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C3411",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "mybank",
        testId: "C420294",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "mybank",
        testId: "C420295",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "mybank",
        testId: "C420296",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "mybank",
        testId: "C420297",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "paypal",
        testId: "C3392",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "paypal",
        testId: "C3393",
        mollieStatus: "Pending",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.methodName.toLowerCase()),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "paypal",
        testId: "C3394",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "paypal",
        testId: "C3395",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "paypal",
        testId: "C3396",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "paysafecard",
        testId: "C420141",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "paysafecard",
        testId: "C420142",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "paysafecard",
        testId: "C420143",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C3424",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C3425",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C3426",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C3427",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "banktransfer",
        testId: "C3433",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "banktransfer",
        testId: "C3432",
        mollieStatus: "Open",
        wooStatus: "ON-HOLD",
        notice: context => noticeLines.open(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "banktransfer",
        testId: "C3434",
        mollieStatus: "Expired",
        wooStatus: "ON-HOLD",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "sofort",
        testId: "C3405",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "sofort",
        testId: "C3407",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "sofort",
        testId: "C3406",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    /*{
        methodId: "alma",
        testId: "",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "alma",
        testId: "",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "alma",
        testId: "",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "alma",
        testId: "",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "trustly",
        testId: "",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "trustly",
        testId: "",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "trustly",
        testId: "",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "trustly",
        testId: "",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "payconiq",
        testId: "",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "payconiq",
        testId: "",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "payconiq",
        testId: "",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "payconiq",
        testId: "",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "riverty",
        testId: "",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "riverty",
        testId: "",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "riverty",
        testId: "",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "riverty",
        testId: "",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },*/
];
