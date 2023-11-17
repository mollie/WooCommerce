const {noticeLines, checkExpiredAtMollie} = require("../../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage} = require("../../Shared/testMollieInWooPage");
export const testData = [
    {
        methodId: "bancontact",
        testId: "C420230",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "bancontact",
        testId: "C420231",
        mollieStatus: "Open",
        wooStatus: "Pending",
        notice: context => noticeLines.open(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "bancontact",
        testId: "C420232",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "bancontact",
        testId: "C420233",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "bancontact",
        testId: "C420234",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "belfius",
        testId: "C420298",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "belfius",
        testId: "C420299",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "belfius",
        testId: "C420300",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "belfius",
        testId: "C420301",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "eps",
        testId: "C420260",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "eps",
        testId: "C420261",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "eps",
        testId: "C420262",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "eps",
        testId: "C420263",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "giropay",
        testId: "C420290",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "giropay",
        testId: "C420291",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "giropay",
        testId: "C420292",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "giropay",
        testId: "C420293",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "ideal",
        testId: "C420244",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "ideal",
        testId: "C420245",
        mollieStatus: "Open",
        wooStatus: "Pending",
        notice: context => noticeLines.open(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "ideal",
        testId: "C420246",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "ideal",
        testId: "C420248",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "ideal",
        testId: "C420247",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "kbc",
        testId: "C420267",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "kbc",
        testId: "C420264",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "kbc",
        testId: "C420265",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "kbc",
        testId: "C420266",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C420249",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C420250",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C420251",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaylater",
        testId: "C420252",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C420279",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C420280",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C420281",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnapaynow",
        testId: "C420282",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C420223",
        mollieStatus: "Authorized",
        wooStatus: "Processing",
        notice: context => noticeLines.authorized(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C420224",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C420225",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "klarnasliceit",
        testId: "C420226",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "mybank",
        testId: "C420286",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "mybank",
        testId: "C420287",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "mybank",
        testId: "C420288",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "mybank",
        testId: "C420289",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "paypal",
        testId: "C420253",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "paypal",
        testId: "C420254",
        mollieStatus: "Pending",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.methodName.toLowerCase()),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "paypal",
        testId: "C420255",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "paypal",
        testId: "C420256",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "paypal",
        testId: "C420257",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "paysafecard",
        testId: "C420306",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "paysafecard",
        testId: "C420307",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "paysafecard",
        testId: "C420308",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C420235",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C420236",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C420237",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "przelewy24",
        testId: "C420238",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
    {
        methodId: "bancontact",
        testId: "C420284",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "sofort",
        testId: "C420227",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "sofort",
        testId: "C420229",
        mollieStatus: "Canceled",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
    {
        methodId: "sofort",
        testId: "C420228",
        mollieStatus: "Expired",
        wooStatus: "Pending",
        notice: context => noticeLines.expired(context.method.id),
        action: async (page) => {
            await checkExpiredAtMollie(page);
        }
    },
];
