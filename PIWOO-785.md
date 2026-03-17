Merchant ID: 16951859

The merchant reported that after creating manual Subscriptions on behalf of consumers  are generating duplicate payments for the same order.
After resolving a SiteGround-related issue, current subscriptions are processing correctly, and webhooks are being sent. However the merchant is currently creating manual subscriptions for affected customers and canceling duplicates once identified.

Steps to Reproduce:
Create a yearly subscription with iDEAL as the payment method.
Complete the checkout process.
Observe that two payments are created for the same order.

Expected Result:
Only one payment should be created per order/subscription.

Actual Result:
Two payments are created for the same order, causing duplicate transactions.

Additional Information:
Subscription frequency: Yearly
Amount: €34.95
Webhooks: Sent successfully

---
Task:

I need a mu-plugin that registers a cli command that sets up a subscription order paid with ideal through mollie as described in the support ticket.
It should create a logfile identified by the order number (in the filename).
Then all relevant webhooks should be logged to that file and any outgoing api requests from the mollie plugin.
