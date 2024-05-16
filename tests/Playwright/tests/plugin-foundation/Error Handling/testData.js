export const testData = [
    {
        testId: "C419987",
        mollieStatus: "Paid",
        searchLine: "onWebhookPaid processing paid order via Mollie plugin fully completed"
    },
    {
        testId: "C420052",
        mollieStatus: "Authorized",
        searchLine: "onWebhookAuthorized called for order",
    },
    {
        testId: "C420052",
        mollieStatus: "Open",
        searchLine: "Customer returned to store, but payment still pending for order",
    },
    {
        testId: "C419988",
        mollieStatus: "Failed",
        searchLine: "onWebhookFailed called for order",
    },
    {
        testId: "C420050",
        mollieStatus: "Canceled",
        searchLine: "Pending payment",
    },
    {
        testId: "C420051",
        mollieStatus: "Expired",
        searchLine: "Pending payment",
    },
    {
        testId: "C420054",
        mollieStatus: "Pending",
        wooStatus: "Pending payment",
    },
];
