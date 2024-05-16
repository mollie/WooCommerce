const {noticeLines} = require("../../Shared/mollieUtils");
const {wooOrderPaidPage, wooOrderRetryPage} = require("../../Shared/testMollieInWooPage");
export const testData = [
    {
        methodId: "belfius",
        testId: "C420409",
        mollieStatus: "Paid",
        wooStatus: "Processing",
        notice: context => noticeLines.paid(context.methodName),
        action: async (page, result, context) => {
            await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
        }
    },
    {
        methodId: "belfius",
        testId: "C420410",
        mollieStatus: "Failed",
        wooStatus: "Pending",
        notice: context => noticeLines.failed(context.method.id),
        action: async (page) => {
            await wooOrderRetryPage(page);
        }
    },
   {
       methodId: "belfius",
       testId: "C420411",
       mollieStatus: "Canceled",
       wooStatus: "Pending",
       notice: context => noticeLines.failed(context.method.id),
       action: async (page) => {
           await wooOrderRetryPage(page);
       }
   },
   {
       methodId: "belfius",
       testId: "C420412",
       mollieStatus: "Expired",
       wooStatus: "Pending",
       notice: context => noticeLines.expired(context.method.id),
       action: async (page) => {
           await wooOrderRetryPage(page);
       }
   },
  {
      methodId: "bancontact",
      testId: "C420345",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "bancontact",
      testId: "C420346",
      mollieStatus: "Open",
      wooStatus: "Pending",
      notice: context => noticeLines.open(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "bancontact",
      testId: "C420347",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "bancontact",
      testId: "C420348",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "bancontact",
      testId: "C420349",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: 'eps',
      testId: "C420375",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: 'eps',
      testId: "C420376",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: 'eps',
      testId: "C420377",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: 'eps',
      testId: "C420378",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "giropay",
      testId: "C420405",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "giropay",
      testId: "C420406",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "giropay",
      testId: "C420407",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "giropay",
      testId: "C420408",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "ideal",
      testId: "C420359",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "ideal",
      testId: "C420360",
      mollieStatus: "Open",
      wooStatus: "Pending",
      notice: context => noticeLines.open(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "ideal",
      testId: "C420361",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "ideal",
      testId: "C420363",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "ideal",
      testId: "C420362",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "kbc",
      testId: "C420379",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "kbc",
      testId: "C420380",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "kbc",
      testId: "C420381",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "kbc",
      testId: "C420382",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnapaylater",
      testId: "C420364",
      mollieStatus: "Authorized",
      wooStatus: "Processing",
      notice: context => noticeLines.authorized(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "klarnapaylater",
      testId: "C420365",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnapaylater",
      testId: "C420366",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnapaylater",
      testId: "C420367",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnapaynow",
      testId: "C420394",
      mollieStatus: "Authorized",
      wooStatus: "Processing",
      notice: context => noticeLines.authorized(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "klarnapaynow",
      testId: "C420395",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnapaynow",
      testId: "C420396",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnapaynow",
      testId: "C420397",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnasliceit",
      testId: "C420338",
      mollieStatus: "Authorized",
      wooStatus: "Processing",
      notice: context => noticeLines.authorized(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "klarnasliceit",
      testId: "C420339",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnasliceit",
      testId: "C420340",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "klarnasliceit",
      testId: "C420341",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "mybank",
      testId: "C420401",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "mybank",
      testId: "C420402",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "mybank",
      testId: "C420403",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "mybank",
      testId: "C420404",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "paypal",
      testId: "C420368",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "paypal",
      testId: "C420369",
      mollieStatus: "Pending",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "paypal",
      testId: "C420370",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "paypal",
      testId: "C420371",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "paypal",
      testId: "C420372",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "paysafecard",
      testId: "C420417",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "paysafecard",
      testId: "C420418",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "paysafecard",
      testId: "C420419",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "przelewy24",
      testId: "C420350",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "przelewy24",
      testId: "C420351",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "przelewy24",
      testId: "C420352",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "przelewy24",
      testId: "C420353",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "banktransfer",
      testId: "C420399",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "banktransfer",
      testId: "C420398",
      mollieStatus: "Open",
      wooStatus: "on-hold",
      notice: context => noticeLines.open(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "banktransfer",
      testId: "C420400",
      mollieStatus: "Expired",
      wooStatus: "on-hold",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "sofort",
      testId: "C420342",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "sofort",
      testId: "C420344",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "sofort",
      testId: "C420343",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "billie",
      testId: "C420413",
      mollieStatus: "Authorized",
      wooStatus: "Processing",
      notice: context => noticeLines.authorized(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "billie",
      testId: "C420414",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "billie",
      testId: "C420415",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "billie",
      testId: "C420416",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "in3",
      testId: "C420334",
      mollieStatus: "Paid",
      wooStatus: "Processing",
      notice: context => noticeLines.paid(context.methodName),
      action: async (page, result, context) => {
          await wooOrderPaidPage(page, result.mollieOrder, result.totalAmount, context.method);
      }
  },
  {
      methodId: "in3",
      testId: "C420335",
      mollieStatus: "Failed",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "in3",
      testId: "C420336",
      mollieStatus: "Canceled",
      wooStatus: "Pending",
      notice: context => noticeLines.failed(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
  {
      methodId: "in3",
      testId: "C420337",
      mollieStatus: "Expired",
      wooStatus: "Pending",
      notice: context => noticeLines.expired(context.method.id),
      action: async (page) => {
          await wooOrderRetryPage(page);
      }
  },
];
